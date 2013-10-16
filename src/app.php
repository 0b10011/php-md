<?php

namespace bfrohs\markdown;

require_once(MARKDOWN_SOURCE."/exceptions.php");

class Markdown {
	
	/**
	 * Stores original text value provided to constructor
	 * 
	 * @var string 
	 */
	protected $markdown = null;
	
	protected $encoding = "UTF-8";
	
	public function __construct($text, array $options = null){
		if($options){
			$this->setOptions($options);
		}
		
		$this->markdown = $text;
		
		$tokenizer = new tokenizer($this->markdown, $this->encoding);
		
		$parser = new parser($tokenizer->getTokens());
		
		$this->parse_tree = $parser->getTree();
	}
	
	protected function setOptions(array $options){
		throw(new RuntimeException("Cannot set any options yet, sorry! :("));
	}
	
	public function toHTML(){
		$html = "";
		
		foreach($this->parse_tree as $parse){
			$type = array_key_exists(0, $parse) ? $parse[0] : null;
			$value = array_key_exists(1, $parse) ? $parse[1] : null;
			
			if($type==="character"){
				$html .= $value;
			} elseif($type==="startEm"){
				$html .= "<em>";
			} elseif($type==="endEm"){
				$html .= "</em>";
			} elseif($type==="startStrong"){
				$html .= "<strong>";
			} elseif($type==="endStrong"){
				$html .= "</strong>";
			} elseif($type==="newline"){
				$html .= "<br>";
			} elseif($type==="startParagraph"){
				$html .= "<p>";
			} elseif($type==="endParagraph"){
				$html .= "</p>";
			} else {
				$html .= implode(":", $parse)."|";
			}
		}
		
		return $html;
	}
}

class Tokenizer {
	
	protected $markdown = null;
	protected $encoding = null;
	protected $tokens = array();
	protected $position = 0;
	protected $last_consumed = false;
	protected $state = "start";
	protected $states = array(
		"start" => "start",
		"mol" => "mol",
		"newLine" => "newLine",
		"afterNewLine" => "afterNewLine",
		"startParagraph" => "startParagraph",
		"afterSpace" => "afterSpace",
	);
	
	public function __construct($markdown, $encoding){
		$this->markdown = $markdown;
		$this->encoding = $encoding;
		
		$length = mb_strlen($this->markdown);
		while($this->position<$length){
			if(!array_key_exists($this->state, $this->states)){
				throw(new OutOfRangeException("`$this->state` is not a valid state"));
			}
			$state = $this->states[$this->state];
			$this->$state();
		}
		
		$this->tokens[] = array("endParagraph");
	}
	
	public function getTokens(){
		return $this->tokens;
	}
	
	protected function consume($toConsume = null){
		if($toConsume!==null){
			if(!is_string($toConsume)){
				throw(new InvalidArgumentException("Provided toConsume `$toConsume` was not a string"));
			}
			
			if(mb_strlen($toConsume)!==1){
				throw(new InvalidArgumentException("Provided toConsume `$toConsume` was not 1 character"));
			}
			
			$consumed = 0;
			while(mb_substr($this->markdown, $this->position, 1, $this->encoding)===$toConsume){
				$this->position++;
				$consumed++;
			}
			
			return $consumed;
		}
		
		$ch = mb_substr($this->markdown, $this->position, 1, $this->encoding);
		
		if($ch===""){
			throw(new OutOfRangeException("consume() called but no characters left"));
		}
		
		$this->position++;
		
		return $ch;
	}
	
	protected function backup($steps = 1){
		if(!is_integer($steps)){
			throw(new InvalidArgumentException("# of steps provided `$steps` was not an integer"));
		}
		if($steps<1){
			throw(new InvalidArgumentException("# of steps provided `$steps` was less than 1"));
		}
		if($this->position-$steps<0){
			throw(new OutOfRangeException("backup($steps) called when position is $this->position"));
		}
		
		$this->position = $this->position - $steps;
	}
	
	protected function next(){
		$ch = mb_substr($this->markdown, $this->position, 1, $this->encoding);
		
		return $ch;
	}
	
	protected function start(){
		$ch = $this->consume();

		if($ch==="\n"){
			// Ignore
			return;
		}
		
		$this->backup();
		$this->tokens[] = array("startParagraph");
		$this->state = "mol";
	}
	
	protected function newLine(){
		$ch = $this->consume();
		
		if($ch==="\n"){
			if($this->consume("\n")){
				$this->tokens[] = array("endParagraph");
				$this->tokens[] = array("startParagraph");
				$this->state = "startParagraph";
				return;
			}
			$this->state = "mol";
			$this->tokens[] = array("character", " ");
			return;
		}
		
		if($ch===" "&&$this->consume(" ")){
			$consumed = $this->consume("\n");
			if($consumed===1){
				$this->tokens[] = array("newline");
				$this->state = "afterNewLine";
				return;
			} elseif($consumed){
				$this->tokens[] = array("endParagraph");
				$this->tokens[] = array("startParagraph");
				$this->state = "startParagraph";
				return;
			}
		}
		
		throw(new BadMethodCallException("In newLine state, but ch `$ch` is not a new line"));
	}
	
	protected function afterNewLine(){
		$ch = $this->consume();

		if($ch==="\n"){
			// Ignore
			return;
		}
		
		if($ch===" "){
			// Ignore
			return;
		}
		
		$this->backup();
		$this->state = "mol";
	}
	
	protected function startParagraph(){
		$ch = $this->consume();

		if($ch==="\n"){
			// Ignore
			return;
		}
		
		$this->backup();
		$this->state = "mol";
	}
	
	protected function mol(){
		$ch = $this->consume();
		
		if($ch==="\n"){
			$this->state = "newLine";
			$this->backup();
			return;
		}
		
		if($ch===" "){
			if($this->consume(" ")&&$this->next()==="\n"){
				$this->backup(2);
				$this->state = "newLine";
				return;
			}
			$this->tokens[] = array("character", $ch);
			$this->state = "afterSpace";
			return;
		}
		
		if($ch==="*"){
			$consumed = $this->consume("*");
			if($consumed>2){
				$this->position = $this->position - ($consumed - 2);
				$consumed = 2;
			}
			if($consumed===2){
				$this->tokens[] = array("endEm");
				$this->tokens[] = array("endStrong");
				return;
			} elseif($consumed===1){
				$this->tokens[] = array("endStrong");
				return;
			}
			$this->tokens[] = array("endEm");
			return;
		}
		
		$this->tokens[] = array("character", $ch);
	}
	
	protected function afterSpace(){
		$ch = $this->consume();
		
		if($ch==="*"){
			$next = $this->next();
			if($next==="*"){
				$this->consume();
				$this->tokens[] = array("startStrong");
				return;
			}
			$this->tokens[] = array("startEm");
			return;
		}
		
		$this->backup();
		$this->state = "mol";
	}
}

class Parser {
	protected $tokens = null;
	protected $tree = null;
	protected $position = 0;
	protected $state = "data";
	protected $states = array(
		"data" => "data",
		"afterSpace" => "afterSpace",
		"afterNewline" => "afterNewline",
	);
	
	public function __construct(array $tokens){
		$this->tokens = $tokens;
		
		$count = count($this->tokens);
		while($this->position<$count){
			if(!array_key_exists($this->state, $this->states)){
				throw(new OutOfRangeException("`$this->state` is not a valid state"));
			}
			$state = $this->states[$this->state];
			$this->$state();
		}
	}
	
	public function getTree(){
		return $this->tree;
	}
	
	protected function consume(){
		if(!array_key_exists($this->position, $this->tokens)){
			throw(new OutOfRangeException("consume() called but no tokens left"));
		}
		
		$token = $this->tokens[$this->position];
		
		$this->position++;
		
		return $token;
	}
	
	protected function backup($steps = 1){
		if(!is_integer($steps)){
			throw(new InvalidArgumentException("# of steps provided `$steps` was not an integer"));
		}
		if($steps<1){
			throw(new InvalidArgumentException("# of steps provided `$steps` was less than 1"));
		}
		if($this->position-$steps<0){
			throw(new OutOfRangeException("backup($steps) called when position is $this->position"));
		}
		
		$this->position = $this->position - $steps;
	}
	
	protected function next(){
		if(!array_key_exists($this->position, $this->tokens)){
			return null;
		}
		
		$token = $this->tokens[$this->position];
		
		return $token;
	}
	
	protected $open_elements = array();
	protected function data(){
		$token = $this->consume();
		
		if($token[0]==="startParagraph"){
			$this->open_elements[] = "p";
			$this->tree[] = $token;
			return;
		}
		
		if($token[0]==="character"&&$token[1]===" "){
			$this->state = "afterSpace";
		}
		
		if($token[0]==="newline"){
			$this->state = "afterNewline";
		}
		
		$this->tree[] = $token;
	}
	
	protected function afterSpace(){
		while($next = $this->next()){
			if($next[0]!=="character"||$next[1]!==" "){
				break;
			}
			$this->consume();
		}
		$this->state = "data";
		return;
	}
	
	protected function afterNewline(){
		while($next = $this->next()){
			if($next[0]!=="character"||$next[1]!==" "){
				break;
			}
			$this->consume();
		}
		$this->state = "data";
		return;
	}
}
