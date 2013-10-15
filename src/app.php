<?php

namespace bfrohs\markdown;

require_once(MARKDOWN_SOURCE.'/exceptions.php');

class Markdown {
	
	/**
	 * Stores original text value provided to constructor
	 * 
	 * @var string 
	 */
	protected $markdown = null;
	
	protected $encoding = 'UTF-8';
	
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
		$html = '';
		
		foreach($this->parse_tree as $parse){
			$html .= $parse.'-';
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
	protected $state = 'sol';
	protected $states = array(
		'sol' => 'sol',
		'list' => 'listState',
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
	}
	
	public function getTokens(){
		return $this->tokens;
	}
	
	protected function consume(){
		$ch = mb_substr($this->markdown, $this->position, 1, $this->encoding);
		
		if($ch===''){
			throw(new OutOfRangeException("consume() called but not characters left"));
		}
		
		$this->position++;
		
		return $ch;
	}
	
	protected function next(){
		$ch = mb_substr($this->markdown, $this->position, 1, $this->encoding);
		
		return $ch;
	}
	
	protected function sol(){
		$ch = $this->consume();

		if($ch==="\n"){
			$this->tokens[] = array("newline", $ch);
			return;
		}
		
		if(($ch==="*"||$ch==="-"||$ch==="+")&&$this->next()===" "){
			$this->tokens[] = array("list", $ch.$this->consume());
			$this->state = 'list';
			return;
		}
		
		$this->tokens[] = array("character", $ch);
	}
	
	protected function listState(){
		$ch = $this->consume();
		
		if($ch==="\n"){
			$this->tokens[] = array("newline", $ch);
			$this->state = 'sol';
			return;
		}
		
		$this->tokens[] = array("character", $ch);
	}
}

class Parser {
	protected $tokens = null;
	protected $tree = null;
	protected $position = 0;
	protected $state = 'data';
	protected $states = array(
		'data' => 'data',
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
	
	protected function data(){
		$token = $this->consume();
		
		if($token[0]==='list'){
			$this->tree[] = 'list';
			return;
		}
		
		$this->tree[] = $token[1];
	}
}
