<?php

namespace bfrohs\markdown;

require_once(__DIR__."/exceptions.php");

class Markdown {
	
	/**
	 * Stores original text value provided to constructor
	 * 
	 * @var string 
	 */
	protected $markdown = null;
	
	protected $encoding = "UTF-8";
	
	protected $parse_tree = null;
	
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
	
	/**
	 * Converts a parse tree (starting from #ROOT) into HTML.
	 * @return string Returns parse tree as HTML.
	 */
	public function toHTML(){
		return $this->nodesToHTML($this->parse_tree["children"]);
	}
	
	/**
	 * Converts array of nodes into HTML.
	 * @param array $nodes
	 * @return string Returns nodes as HTML.
	 */
	protected function nodesToHTML($nodes){
		$html = "";
		
		foreach($nodes as $node){
			if($node["name"]==="#TEXT"){
				$html .= $this->escapeStringForHtml($node["value"]);
			} else {
				$html .= $this->nodeToHTML($node);
			}
		}
		
		return $html;
	}
	
	/**
	 * Converts a single node into HTML.
	 * @param array $node
	 * @return string Returns node as HTML.
	 */	
	protected function nodeToHTML($node){
		$html = "";
		
		$name = $node["name"];
		
		$attributes = $this->attributesToHTML($node["attributes"]);
		
		$html .= "<$name$attributes>";
		
		if($node["empty"]){
			return $html;
		}
		
		$html .= $this->nodesToHTML($node["children"]);
		
		$html .= "</$name>";
		
		return $html;
	}
	
	/**
	 * Converts array of attributes and values into HTML.
	 * @param array $attributes Array("attribute" => "value", ...)
	 * @return string Returns attributes as HTML.
	 */
	protected function attributesToHTML($attributes){
		$html = "";
		
		ksort($attributes);
		foreach($attributes as $attribute => $value){
			$escaped_value = htmlspecialchars($value);
			
			$html .= " $attribute=\"$escaped_value\"";
		}
		
		return $html;
	}
	
	/**
	 * 
	 * @param string $string String to be escaped.
	 * @return string Returns string escaped for placement as text in HTML. That is, this
	 * is not appropriate for strings that will be part of an HTML attribute value.
	 */
	protected function escapeStringForHtml($string){
		
		// Escape & (first to avoid double replacements)
		$string = str_replace("&", "&amp;", $string);
		
		// Escape < (> doesn't need to be escaped)
		$string = str_replace("<", "&lt;", $string);
		
		return $string;
	}
}

class Tokenizer {
	
	protected $markdown = null;
	protected $encoding = null;
	protected $tokens = array();
	protected $position = 0;
	protected $last_consumed = false;
	protected $loop_detect = array();
	protected $state = "start";
	protected $states = array(
		"start" => "start",
		"textStart" => "textStart",
		"textStartNext" => "textStartNext",
		"textLineStart" => "textLineStart",
		"text" => "text",
		"textAfterSpace" => "textAfterSpace",
		"indentedCode" => "indentedCode",
		
		
//		"startBlock" => "startBlock",
//		"hardLine" => "hardLine",
//		"softLine" => "softLine",
//		"inLine" => "inLine",
//		"afterSpace" => "afterSpace",
		"startCode" => "startCode",
		"inCode" => "inCode",
		"atxHeader" => "atxHeader",
		"startLink" => "startLink",
		"linkText" => "linkText",
		"linkUrl" => "linkUrl",
		"linkTitle" => "linkTitle",
		"startImage" => "startImage",
		"imageAlt" => "imageAlt",
		"imageUrl" => "imageUrl",
		"imageTitle" => "imageTitle",
		"ul" => "ul",
		"ol" => "ol",
	);
	
	public function __construct($markdown, $encoding){
		$this->markdown = str_replace("\t", "    ", $markdown);
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
				if(!array_key_exists($this->position, $this->loop_detect)){
					$this->loop_detect[$this->position] = 1;
				} else {
					$this->loop_detect[$this->position]++;
				}
				if($this->loop_detect[$this->position]>5){
					throw(new LogicException("Infinite loop detected at position $this->position"));
				}
				
				$this->position++;
				$consumed++;
			}
			
			return $consumed;
		}
		
		$ch = mb_substr($this->markdown, $this->position, 1, $this->encoding);
		
		if(!array_key_exists($this->position, $this->loop_detect)){
			$this->loop_detect[$this->position] = 1;
		} else {
			$this->loop_detect[$this->position]++;
		}
		if($this->loop_detect[$this->position]>5){
			throw(new LogicException("Infinite loop detected at position $this->position"));
		}
		
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
	
	protected function next($steps = 1){
		if(!is_integer($steps)){
			throw(new InvalidArgumentException("# of steps provided `$steps` was not an integer"));
		}
		if($steps<1){
			throw(new InvalidArgumentException("# of steps provided `$steps` was less than 1"));
		}
		
		// Adjust $steps by 1 so we don't have to worry about it later
		$steps--;
		
		if($this->position + $steps > mb_strlen($this->markdown)){
			return null;
		}
		
		$ch = mb_substr($this->markdown, $this->position + $steps, 1, $this->encoding);
		
		return $ch;
	}
	
	protected function match($pattern){
		$matches = null;
		preg_match("/^$pattern/", mb_substr($this->markdown, $this->position), $matches);
		return $matches;
	}
	
	protected function addToken($type, $value = null, $super_block = false){
		if(!$super_block){
			$this->only_super_blocks = false;
		}
		$this->line_tokens[] = array($type, $value);
		$this->tokens[] = array($type, $value);
	}
	
	protected $saved_tokens = array();
	protected $line_tokens_last = array();
	protected $line_tokens = array();
	protected $only_super_blocks = true;
	
	/**
	 * Tracks whether we're currently in a list
	 * @var bool 
	 */
	protected $in_list = false;
	
	protected function sameLinePrefix($current, $previous){
		foreach($current as $key => $this_current){
			if($this_current!==$previous[$key]){
				return false;
			}
		}
		return true;
	}
	
	protected function startOfLine(){
		$reset = false;
		if($this->only_super_blocks&&$this->sameLinePrefix($this->line_tokens, $this->line_tokens_last)){
			$this->tokens = $this->saved_tokens;
			$reset = true;
		} else {
			$this->saved_tokens = $this->tokens;
			$this->only_super_blocks = true;
		}
		$this->line_tokens_last = $this->line_tokens;
		$this->line_tokens = array();
		return $reset;
	}
	
	protected function start(){
		// Ignore leading newlines
		while($this->match(" *\n")){
			$this->consume(" ");
			$this->consume("\n");
		}
		
		// Add two newline tokens (to get parser started in general block)
		$this->addToken("newline");
		$this->addToken("newline");
		
		// Switch to text state
		$this->state = "textStart";
	}
	
	/**
	 * Blank state (eg, start or two newlines after a normal paragraph)
	 */
	protected function textStart(){
		
		// Check if indented code
		if($this->match("    ")){
			$this->state = "indentedCode";
			return;
		}
		
		// Ignore leading whitespace
		$this->consume(" ");
		
		$this->state = "textStartNext";
	}
	
	protected function textStartNext(){
		return $this->textLineStart(true);
	}
	
	protected function textLineStart($new_block = false){
		
		// Consume a character
		$ch = $this->consume();
		
		// Atx headers
		if($ch==="#"){
			$this->backup();
			$this->state = "atxHeader";
			return;
		}
		
		if($ch===">"){
			$level = 1;
			do {
				$this->consume(" "); // Ignore whitespace
				$level += $matched = $this->consume(">");
			} while($matched);
			
			if($this->next()==="\n"){
				$this->state = "textStart";
				return;
			}
			
			for($i=0;$i<$level;$i++){
				$this->addToken("blockquote");
			}
			
			return;
		}
		
		// Horizontal rules (---, ***)
		if($ch==="-"||$ch==="*"){
			
			// Loop through line and consume $ch (ignoring spaces)
			$consumed = 1;
			$skipped = 0;
			do {
				$skipped += $this->consume(" ");
				$consumed += $found = $this->consume($ch);
			} while($found);
			
			// If at least 3 $ch were consumed and followed by at least one new line, add "rule" token
			if($consumed>=3&&$this->match(" *\n")){
				while($this->match(" *\n")){
					$this->consume(" ");
					$this->consume("\n");
				}
				$this->addToken("rule", $ch);
				return;
			}
			
			// Not found, back up to $ch
			$backup = $skipped + $consumed - 1;
			if($backup) $this->backup($backup);
			unset($consumed, $skipped, $found);
		}
		
		if(($ch==="*"||$ch==="-"||$ch==="+")&&$this->next()===" "){
			if($new_block||$this->in_list){
				$this->backup();
				$this->state = "ul";
				return;
			}
		}

		if(preg_match("/\d/", $ch)&&$this->match("\d*\. ")){
			if($new_block||$this->in_list){
				$this->backup();
				$this->state = "ol";
				return;
			}
		}
		
		// Backup and move to text state
		$this->backup();
		$this->state = "textAfterSpace";
	}
	
	protected function text(){
		
		// Consume a character
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			$this->addToken("character", $ch);
			return;
		}
		
		if($ch==="\n"){
			$consumed = false;
			while($this->match(" *\n")){
				$this->consume(" ");
				$this->consume("\n");
				$consumed = true;
			}
			if($consumed){
				$this->state = "textStart";
				$this->addToken("newline");
				$this->addToken("newline");
				return;
			}
			
			$this->consume(" ");
			$this->state = "textLineStart";
			$this->addToken("newline");
			return;
		}
		
		if($ch===" "){
			
			if($this->consume(" ")&&$this->next()==="\n"){
				
				// Consume \n
				$this->consume();
				
				$consumed = false;
				while($this->match(" *\n")){
					$this->consume(" ");
					$this->consume("\n");
					$consumed = true;
				}
				
				if($consumed){
					$this->state = "textStart";
					$this->addToken("newline");
					$this->addToken("newline");
					return;
				}
				
				$this->state = "textLineStart";
				$this->addToken("linebreak");
				$this->addToken("newline");
				
				// Ignore leading whitespace
				$this->consume(" ");
				
				return;
			}
			
			if($this->next()==="\n"){
				return;
			}
			
			$this->addToken("character", $ch);
			$this->state = "textAfterSpace";
			return;
		}
		
		if($ch==="*"||$ch==="_"){
			$consumed = $this->consume($ch);
			$type = $this->next() === " " ? "end" : "toggle";
			if($consumed>2){
				$this->position = $this->position - ($consumed - 2);
				$consumed = 2;
			}
			if($consumed===2){
				$this->addToken($type."Em", $ch);
				$this->addToken($type."Strong", $ch.$ch);
				return;
			} elseif($consumed===1){
				$this->addToken($type."Strong", $ch.$ch);
				return;
			}
			$this->addToken($type."Em", $ch);
			return;
		}
		
		if($ch==="`"){
			$this->backup();
			$this->state = "startCode";
			return;
		}
		
		if($ch==="!"&&$this->match("\\[[^\\]\\n]*\\]\\([^\"\\)\\n]+(\"([^\"\\\\]+|\\\\\\\\|\\\\.)+\")?\\)")){
			$this->state = "startImage";
			$this->backup();
			return;
		}
		
		if($ch==="["&&$this->match("[^\\]\\n]+\\]\\([^\"\\)\\n]*(\"([^\"\\\\]+|\\\\\\\\|\\\\.)+\")?\\)")){
			$this->state = "startLink";
			$this->backup();
			return;
		}
		
		// Plain text, append as text
		$this->addToken("character", $ch);
	}
	
	protected function textAfterSpace(){
		$ch = $this->consume();
		$this->state = "text";
		
		if(($ch==="*"||$ch==="_")&&!$this->match("[ \n]")){
			$consumed = $this->consume($ch);
			if($consumed>2){
				$this->position = $this->position - ($consumed - 2);
				$consumed = 2;
			}
			if($consumed===2){
				$this->addToken("startEm", $ch);
				$this->addToken("startStrong", $ch.$ch);
				return;
			} elseif($consumed===1){
				$this->addToken("startStrong", $ch.$ch);
				return;
			}
			$this->addToken("startEm", $ch);
			return;
		}
		
		$this->backup();
	}
	
	protected function indentedCode(){
		// Consume all blank lines
		$newline = false;
		while($this->match(" *\n")){
			$this->consume(" ");
			$this->consume("\n");
			$newline = true;
		}
		
		// If a new, non-blank line and not indented, start a new block
		if($newline&&!$this->match("    ")){
			$this->addToken("newline");
			$this->addToken("newline");
			$this->state = "text";
			return;
		}
		
		// Consume a character
		$ch = $this->consume();
		
		// Plain text, append as text
		$this->addToken("character", $ch);
	}
	
	protected function startBlock(){
		throw(new LogicException("startBlock is not valid"));
		$this->startOfLine();
		
		$this->addToken("startBlock");
		$this->in_list = false;

		// Ignore blank lines
		while($this->match(" *\n")){
			$this->consume(" ");
			$this->consume("\n");
		}
		
		return $this->startLine(true);
	}
	
	protected function hardLine(){
		if($this->startOfLine()){
			$this->state = "startBlock";
			return;
		}
		
		$this->addToken("newLine");
		
		return $this->startLine();
	}
	
	protected function softLine(){
		if($this->startOfLine()){
			$this->state = "startBlock";
			return;
		}
		
		return $this->startLine(false, false);
	}
	
	protected function startLine($new_block = false, $hard_break = true){
		// Ignore blank lines
		while($this->match(" *\n")){
			$this->consume(" ");
			$this->consume("\n");
		}
		
		// Add indent token for every 4 spaces (1 tab = 4 spaces)
		$spaces = $this->consume(" ");
		$indentation = floor($spaces / 4);
		for($i=0;$i<$indentation;$i++){
			$this->addToken("indent");
		}
		
		$ch = $this->consume();
		
		if($ch==="-"||$ch==="*"){
			
			// Loop through line and consume $ch (ignoring spaces)
			$consumed = 1;
			$skipped = 0;
			do {
				$skipped += $this->consume(" ");
				$consumed += $found = $this->consume($ch);
			} while($found);
			
			// If at least 3 $ch were consumed and followed by at least one new line, add "rule" token
			if($consumed>=3&&$this->next()==="\n"){
				$this->addToken("rule", $ch);
				$this->state = "startBlock";
				$this->in_list = false;
				return;
			}
			
			// Not found, back up to $ch
			$backup = $skipped + $consumed - 1;
			if($backup) $this->backup($backup);
			unset($consumed, $skipped, $found);
		}
		
		if(($ch==="*"||$ch==="-"||$ch==="+")&&$this->next()===" "){
			if($new_block||$this->in_list){
				$this->backup();
				$this->state = "ul";
				return;
			}
		}

		if(preg_match("/\d/", $ch)&&$this->match("\d*\. ")){
			if($new_block||$this->in_list){
				$this->backup();
				$this->state = "ol";
				return;
			}
		}
		
		if($ch==="#"){
			$this->backup();
			$this->state = "atxHeader";
			return;
		}
		
		if($ch===">"){
			$level = 1;
			do {
				$this->consume(" "); // Ignore whitespace
				$level += $matched = $this->consume(">");
			} while($matched);
			
			if($this->next()==="\n"){
				$this->state = "startBlock";
				return;
			}
			
			for($i=0;$i<$level;$i++){
				$this->addToken("startBlockquote");
			}
			
			$this->state = "afterSpace";
			return;
		}
		
		$this->backup();
		$this->state = "afterSpace";
		
		// If a soft break and we've made it this far, add a space character
		if(!$hard_break){
			$this->addToken("character", " ");
		}
	}
	
	protected function inLine(){
		$ch = $this->consume();
		
		if($ch==="\n"){
			// Consume newlines (ignoring whitespace on blank lines)
			$this->state = "softLine";
			while($this->match(" *\n")){
				$this->consume(" ");
				$this->consume("\n");
				$this->state = "startBlock";
			}
			return;
		}
		
		if($ch===" "&&$this->consume(" ")&&$this->consume("\n")){
			$this->state = "hardLine";
			return;
		}
		
		if($ch==="\\"){
			$ch = $this->consume();
			$this->addToken("character", $ch);
			return;
		}
		
		if($ch==="`"){
			$this->backup();
			$this->state = "startCode";
			return;
		}
		
		if($ch==="!"&&$this->match("\\[[^\\]\\n]*\\]\\([^\"\\)\\n]+(\"([^\"\\\\]+|\\\\\\\\|\\\\.)+\")?\\)")){
			$this->state = "startImage";
			$this->backup();
			return;
		}
		
		if($ch==="["&&$this->match("[^\\]\\n]+\\]\\([^\"\\)\\n]*(\"([^\"\\\\]+|\\\\\\\\|\\\\.)+\")?\\)")){
			$this->state = "startLink";
			$this->backup();
			return;
		}
		
		if($ch===" "){
			$this->addToken("character", $ch);
			$this->state = "afterSpace";
			return;
		}
		
		if($ch==="*"||$ch==="_"){
			$consumed = $this->consume($ch);
			$type = $this->next() === " " ? "end" : "toggle";
			if($consumed>2){
				$this->position = $this->position - ($consumed - 2);
				$consumed = 2;
			}
			if($consumed===2){
				$this->addToken($type."Em", $ch);
				$this->addToken($type."Strong", $ch.$ch);
				return;
			} elseif($consumed===1){
				$this->addToken($type."Strong", $ch.$ch);
				return;
			}
			$this->addToken($type."Em", $ch);
			return;
		}
		
		$this->addToken("character", $ch);
	}
	
	protected function afterSpace(){
		$ch = $this->consume();
		$this->state = "inLine";
		
		if(($ch==="*"||$ch==="_")&&!$this->match("[ \n]")){
			$consumed = $this->consume($ch);
			if($consumed>2){
				$this->position = $this->position - ($consumed - 2);
				$consumed = 2;
			}
			if($consumed===2){
				$this->addToken("startEm", $ch);
				$this->addToken("startStrong", $ch.$ch);
				return;
			} elseif($consumed===1){
				$this->addToken("startStrong", $ch.$ch);
				return;
			}
			$this->addToken("startEm", $ch);
			return;
		}
		
		$this->backup();
	}
	
	protected function ul(){
		$ch = $this->consume();
		
		if($ch!=="*"&&$ch!=="-"&&$ch!=="+"){
			throw(new BadMethodCallException("In ul state, but no *, -, or + could be consumed"));
		}
		
		// Ensure at least one leading space - ignore additional leading spaces
		if(!$this->consume(" ")){
			throw(new BadMethodCallException("In ul state, but no space after $ch could be consumed"));
		}
		
		if(!$this->in_list&&$this->match("[^\\n]*(\\n *){2,}")){
			$this->addToken("ulParagraph", $ch);
		} else {
			$this->addToken("ul", $ch);
		}
		$this->state = "textAfterSpace";
		$this->in_list = true;
	}
	
	protected function ol(){
		$ch = $this->consume();
		
		if(!preg_match("/\d/", $ch)||!$this->match("\d*\. ")){
			throw(new BadMethodCallException("In ol state, but invalid pattern found"));
		}
		
		// Consume all digits and period
		$number = $ch;
		do {
			$number .= $ch = $this->consume();
		} while(preg_match("/\d/", $ch));
		
		// Consume all leading whitespace
		$this->consume(" ");
		
		if(!$this->in_list&&$this->match("[^\\n]*(\\n *){2,}")){
			$this->addToken("olParagraph", $number);
		} else {
			$this->addToken("ol", $number);
		}
		$this->state = "textAfterSpace";
		$this->in_list = true;
	}
	
	protected function atxHeader(){
		$consumed = $this->consume("#");
		
		if(!$consumed){
			throw(new BadMethodCallException("In atxHeader state, but no # could be consumed"));
		}
		
		if($consumed>6){
			$this->backup($consumed-6);
			$consumed = 6;
		}
		
		// Get rid of leading whitespace
		$this->consume(" ");
		
		$this->addToken("atxHeader", "$consumed");
		$this->state = "textAfterSpace";
	}
	
	protected $code_backticks = null;
	protected function startCode(){
		$consumed = $this->consume("`");
		
		if(!$consumed){
			throw(new BadMethodCallException("In code state, but no ` could be consumed"));
		}
		
		$this->code_backticks = $consumed;
		$this->state = "inCode";
		$this->addToken("startCode");
	}
	
	protected function inCode(){
		$consumed = $this->consume("`");
		
		if($consumed > $this->code_backticks){
			$this->backup($consumed - $this->code_backticks);
			$consumed = $this->code_backticks;
		}
		
		if($consumed===$this->code_backticks){
			$this->addToken("closeCode");
			return;
		}
		
		if($consumed) $this->backup($consumed);
		$ch = $this->consume();
		
		$this->addToken("character", $ch);
	}
	
	protected function startLink(){
		$ch = $this->consume();
		
		if($ch!=="["||!$this->match("[^\\]\\n]+\\]\\([^\"\\)\\n]*(\"([^\"\\\\]+|\\\\\\\\|\\\\.)+\")?\\)")){
			throw(new BadMethodCallException("In startLink state, but invalid pattern found"));
		}
		
		$this->state = "linkText";
		$this->addToken("startLink");
	}
	
	protected function linkText(){
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			$this->addToken("character", $ch);
			return;
		}
		
		if($ch==="]"){
			// Ignore spaces
			$this->consume(" ");
			
			// Make sure next character is (
			if($this->next()!=="("){
				throw(new BadMethodCallException("In linkText state, but ( not found after ]"));
			}
			
			// Consume (
			$this->consume();
			$this->state = "linkUrl";
			return;
		}
		
		$this->addToken("character", $ch);
	}
	
	protected function linkUrl($url = ''){
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			return $this->linkUrl($url.$ch);
		}
		
		if($this->match(" *\"")){
			$this->consume(" ");
			$this->consume(); // Consume "
			$this->addToken("linkUrl", $url.$ch);
			$this->state = "linkTitle";
			return;
		}
		
		if($ch===")"){ // Handle empty url
			$this->backup();
			$ch = "";
		}
		if($this->match(" *\)")){
			$this->consume(" ");
			$this->consume(); // Consume )
			$this->addToken("linkUrl", $url.$ch);
			$this->state = "text";
			$this->addToken("endLink");
			return;
		}
		
		return $this->linkUrl($url.$ch);
	}
	
	protected function linkTitle($title = ''){
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			return $this->linkTitle($title.$ch);
		}
		
		if($ch==='"'){
			if($this->consume()!==")"){
				throw(new BadMethodCallException("In linkTitle state, but ) not found after \""));
			}
			$this->addToken("linkTitle", $title);
			$this->state = "text";
			$this->addToken("endLink");
			return;
		}
		
		return $this->linkTitle($title.$ch);
	}
	
	protected function startImage(){
		$ch = $this->consume();
		
		if($ch!=="!"||!$this->match("\\[[^\\]\\n]*\\]\\([^\"\\)\\n]+(\"([^\"\\\\]+|\\\\\\\\|\\\\.)+\")?\\)")){
			throw(new BadMethodCallException("In startImage state, but invalid pattern found"));
		}
		
		$this->consume(); // Consume [
		$this->state = "imageAlt";
		$this->addToken("startImage");
	}
	
	protected function imageAlt($alt = ''){
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			return $this->imageAlt($alt.$ch);
		}
		
		if($ch==="]"){
			// Ignore spaces
			$this->consume(" ");
			
			// Make sure next character is (
			if($this->next()!=="("){
				throw(new BadMethodCallException("In imageAlt state, but ( not found after ]"));
			}
			
			// Consume (
			$this->consume();
			if($alt!=='') $this->addToken("imageAlt", $alt);
			$this->state = "imageUrl";
			return;
		}
		
		return $this->imageAlt($alt.$ch);
	}
	
	protected function imageUrl($url = ''){
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			return $this->imageUrl($url.$ch);
		}
		
		if($this->match(" *\"")){
			$this->consume(" ");
			$this->consume(); // Consume "
			$this->addToken("imageUrl", $url.$ch);
			$this->state = "imageTitle";
			return;
		}
		
		if($this->match(" *\)")){
			$this->consume(" ");
			$this->consume(); // Consume )
			$this->addToken("imageUrl", $url.$ch);
			$this->state = "text";
			$this->addToken("endImage");
			return;
		}
		
		return $this->imageUrl($url.$ch);
	}
	
	protected function imageTitle($title = ''){
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			return $this->imageTitle($title.$ch);
		}
		
		if($ch==='"'){
			if($this->consume()!==")"){
				throw(new BadMethodCallException("In imageTitle state, but ) not found after \""));
			}
			$this->addToken("imageTitle", $title);
			$this->state = "text";
			$this->addToken("endImage");
			return;
		}
		
		return $this->imageTitle($title.$ch);
	}
}

class Parser {
	protected $tokens = null;
	protected $tree = null;
	protected $current = null;
	protected $position = 0;
	protected $loop_detect = array();
	protected $state = "start";
	protected $states = array(
		"start" => "start",
		"startBlock" => "startBlock",
		"block" => "block",
		"paragraph" => "paragraph",
		"inParagraph" => "inParagraph",
		"inlineCode" => "inlineCode",
		
		
		"data" => "data",
		"olContinue" => "olContinue",
		"ulContinue" => "ulContinue",
		"afterSpace" => "afterSpace",
		"afterNewline" => "afterNewline",
		"inImage" => "inImage",
		"inImageAlt" => "inImageAlt",
		"inImageUrl" => "inImageUrl",
		"inImageTitle" => "inImageTitle",
	);
	
	public function __construct(array $tokens){
		$this->tokens = $tokens;
		$this->tree = array(
			"name" => "#ROOT",
			"empty" => false,
			"children" => array(),
		);
		$this->current =& $this->tree;
		
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
		
		if(!array_key_exists($this->position, $this->loop_detect)){
			$this->loop_detect[$this->position] = 1;
		} else {
			$this->loop_detect[$this->position]++;
		}
		if($this->loop_detect[$this->position]>5){
			throw(new LogicException("Infinite loop detected at position $this->position"));
		}
		
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
	protected $element_types = array(
		"blockquote" => "block",
		"ul" => "block",
		"ol" => "block",
		"li" => "block",
		"h1" => "block",
		"h2" => "block",
		"h3" => "block",
		"h4" => "block",
		"h5" => "block",
		"h6" => "block",
		"p" => "block",
		"em" => "formatting",
		"strong" => "formatting",
		"a" => "formatting",
	);
	protected $super_blocks = array(
		"blockquote",
		"ul",
		"ol",
		"li",
	);
	protected $indent_blocks = array(
		"ul" => "ulContinue",
		"ol" => "olContinue",
	);
	protected $blocks = array(
		"p",
		"h1",
		"h2",
		"h3",
		"h4",
		"h5",
		"h6",
		"li",
	);
	protected $formatting = array(
		"em",
		"strong",
		"a",
	);
	
	protected function inBlock(){
		$elt = $this->current;
		$in_block = false;
		do {
			if(in_array($elt["name"], $this->blocks)){
				$in_block = true;
				break;
			}
		} while($elt["name"]!=="#ROOT"&&$elt = $elt["parent"]);
		return $in_block;
	}
	
	protected function appendElement($elt){
		$parent =& $this->current;
		$this->current["children"][] = array(
			"parent" => &$parent,
			"name" => $elt,
			"type" => array_key_exists($elt, $this->element_types) ? $this->element_types[$elt] : "inline",
			"empty" => true,
			"attributes" => array(),
		);
	}
	
	protected function openElement($elt){
		$parent =& $this->current;
		$this->current["children"][] = array(
			"parent" => &$parent,
			"name" => $elt,
			"type" => array_key_exists($elt, $this->element_types) ? $this->element_types[$elt] : "inline",
			"empty" => false,
			"attributes" => array(),
			"children" => array(),
		);
		$this->current =& $this->current["children"][count($this->current["children"])-1];
		
		$this->open_elements[] = $elt;
	}
	
	protected function &getLastElement(){
		if(!count($this->current["children"])){
			$null = null;
			return $null;
		}
		
		return $this->current["children"][count($this->current["children"])-1];
	}
	
	protected function reopenLastElement(){
		$last_element =& $this->current["children"][count($this->current["children"])-1];
		
		if(!array_key_exists("empty", $last_element))throw(new LogicException($last_element["name"]));
		
		if($last_element["empty"]){
			throw(new LogicException("Cannot reopen empty element"));
		}
		
		$this->current =& $last_element;
		
		$this->open_elements[] = $this->current["name"];
	}
	
	protected function closeElement($elt){
		if(!in_array($elt, $this->open_elements)){
			return false;
		}
		
		do {
			if($this->current["name"]==="#ROOT"){
				break;
			}
			
			// If no children, flag for removal
			$remove = null;
			if(!$this->current["children"]){
				$remove =& $this->current;
			}
			
			// Store name to check against $elt later
			$removed = $this->current["name"];
			
			// Set current node to the parent
			$this->current =& $this->current["parent"];
			
			// If flagged for removal, set to NULL, then loop through children of parent and
			// unset NULL child. After unset, reindex children.
			if($remove){
				$remove = null;
				foreach($this->current["children"] as $key => $child){
					if($child===null){
						unset($this->current["children"][$key]);
						break; // Only one could have been set to null above, so no need to keep going
					}
				}
				$this->current["children"] = array_values($this->current["children"]);
			}
		} while($removed!==$elt);
		
		$reopen_formatting = array();
		while($popped = array_pop($this->open_elements)){
			// If nothing popped, we're done, exit loop
			if($popped===NULL){
				break;
			}
			
			$element_type = array_key_exists($popped, $this->element_types) ? $this->element_types[$popped] : "inline";
			
			// If popped element is block, clear $reopen_formatting
			if($element_type==="block"){
				$reopen_formatting = array();
			}
			
			// If popped element is this element, we're done, exit loop
			if($popped===$elt){
				break;
			}
			
			// If popped element type is formatting, add to $reopen_formatting
			if($element_type==="formatting"){
				$reopen_formatting[] = $popped;
			}
		}
		
		// Loop through $reopen_formatting and open all formatting elements listed
		foreach($reopen_formatting as $formatting_element){
			$this->openElement($formatting_element);
		}
		
	}
	
	protected function &getAncestor($elt){
		$current =& $this->current;
		
		while($current["name"]!=="#ROOT"){
			
			if($current["name"]===$elt){
				return $current;
			}
			
			$current =& $current["parent"];
		}
		
		// Not found
		return null;
	}
	
	protected function setAttribute(&$node, $attribute, $value){
		$node["attributes"][$attribute] = $value;
	}
	
	protected function closeBlock(){
		while($this->current["name"]!=="#ROOT"){
			
			if($this->current["type"]==="block"){
				$this->closeElement($this->current["name"]);
				break;
			}
			
			$this->closeElement($this->current["name"]);
		}
	}
	
	protected function closeBlocks(){
		while($this->current["name"]!=="#ROOT"){
			$this->closeElement($this->current["name"]);
		}
	}
	
	protected function appendText($text){
		// Append to previous text node, if it exists
		if($this->current["children"]){
			$child =& $this->current["children"][count($this->current["children"])-1];
			if($child["name"]==="#TEXT"){
				$child["value"] .= $text;
				return;
			}
		}
		
		// No text node found, add a new one
		$parent =& $this->current;
		$this->current["children"][] = array(
			"parent" => &$parent,
			"name" => "#TEXT",
			"type" => "text",
			"value" => $text,
		);
	}
	
	protected function start(){
		$token = $this->consume();
		$next = $this->next();
		if(!array_key_exists(1, $token)){
			$token[1] = null;
		}
		
		if($token[0]==="newline"&&$next[0]==="newline"){
			do {
				$this->consume();
				$next = $this->next();
			} while($next[0]==="newline");
			
			$this->state = "startBlock";
			return;
		}
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected $newlines_for_block = 2;
	protected function startBlock(){
		$this->closeBlocks();
		
		$this->state = "block";
		return $this->block();
	}
	protected function block(){
		$token = $this->consume();
		if(!array_key_exists(1, $token)){
			$token[1] = null;
		}
		
		if($token[0]==="atxHeader"){
			$this->openElement("h$token[1]");
			$this->state = "inParagraph";
			$this->newlines_for_block = 1;
			return;
		}
		
		if($token[0]==="blockquote"){
			$last_element = $this->getLastElement();
			if($last_element&&$last_element["name"]==="blockquote"){
				$this->reopenLastElement();
				return;
			}
			
			$this->openElement("blockquote");
			return;
		}
		
		if($token[0]==="rule"){
			$this->appendElement("hr");
			return;
		}
		
		$this->state = "paragraph";
		$this->backup();
	}
	
	protected function paragraph(){
		$token = $this->consume();
		
		if($token[0]==="ul"||$token[0]==="ulParagraph"){
			if(!in_array("ul", $this->open_elements)){
				$last_element = $this->getLastElement();
				if($last_element&&$last_element["name"]==="ul"){
					$this->reopenLastElement();
				} else {
					$this->openElement("ul");
				}
			}
//			if($increase_list_level){
//				if($this->current["name"]!=="li"){
//					$last_element = $this->getLastElement();
//					if($last_element&&$last_element["name"]==="li"){
//						$this->reopenLastElement();
//					} elseif($this->inBlock()){
//						do {
//							$this->closeBlock();
//						} while($this->current["name"]!=="li"&&$this->inBlock());
//					}
//					if($this->current["name"]!=="li"){
//						$this->openElement("ul");
//						$this->openElement("li");
//					}
//				}
//				$this->openElement("ul");
//			} elseif(in_array("li", $this->open_elements)){
//				$this->closeElement("li");
//			}
			$this->openElement("li");
			if($token[0]==="ulParagraph"){
				$this->openElement("p");
			}
			$this->newlines_for_block = 1;
			$this->state = "inParagraph";
			return;
		}
		
		if($token[0]==="ol"||$token[0]==="olParagraph"){
			if(!in_array("ol", $this->open_elements)){
				$last_element = $this->getLastElement();
				if($last_element&&$last_element["name"]==="ol"){
					$this->reopenLastElement();
				} else {
					$this->openElement("ol");
				}
			}
			if(in_array("li", $this->open_elements)){
				$this->closeElement("li");
			}
			$this->openElement("li");
			if($token[0]==="olParagraph"){
				$this->openElement("p");
			}
			$this->state = "inParagraph";
			$this->newlines_for_block = 1;
			return;
		}
		
		$this->backup();
		
		$this->openElement("p");
		$this->state = "inParagraph";
	}
	
	protected function inParagraph(){
		$token = $this->consume();
		if(!array_key_exists(1, $token)){
			$token[1] = null;
		}
		
		if($token[0]==="character"){
			$this->appendText($token[1]);
			return;
		}
		
		if($token[0]==="newline"){
			$next = $this->next();
			
			// If next token is a newline, consume and switch to block state
			if($next[0]==="newline"){
				$this->consume();
				$this->state = "startBlock";
				return;
			}
			
			// If next token is a rule, switch to block state
			if($next[0]==="rule"){
				$this->state = "startBlock";
				return;
			}
			
			// If only single newline should start new block, switch to block state
			if($this->newlines_for_block===1){
				$this->state = "startBlock";
				$this->newlines_for_block = 2;
				return;
			}
			
			$this->appendText(" ");
			return;
		}
		
		if($token[0]==="linebreak"){
			$this->appendElement("br");
			$this->consume(); // Consume newline
			return;
		}
		
		if($token[0]==="startEm"){
			if(in_array("em", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->openElement("em");
			return;
		}
		
		if($token[0]==="endEm"){
			if(!in_array("em", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->closeElement("em");
			return;
		}
		
		if($token[0]==="toggleEm"){
			if(!in_array("em", $this->open_elements)){
				$this->openElement("em");
				return;
			}
			$this->closeElement("em");
			return;
		}
		
		if($token[0]==="startStrong"){
			if(in_array("strong", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->openElement("strong");
			return;
		}
		
		if($token[0]==="endStrong"){
			if(!in_array("strong", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->closeElement("strong");
			return;
		}
		
		if($token[0]==="toggleStrong"){
			if(!in_array("strong", $this->open_elements)){
				$this->openElement("strong");
				return;
			}
			$this->closeElement("strong");
			return;
		}
		
		if($token[0]==="startCode"){
			$this->state = "inlineCode";
			$this->openElement("code");
			return;
		}
		
		if($token[0]==="startLink"){
			if(in_array("a", $this->open_elements)){
				// Ignore
				return;
			}
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			$this->openElement("a");
			return;
		}
		
		if($token[0]==="endLink"){
			if(!in_array("a", $this->open_elements)){
				// Ignore
				return;
			}
			$this->closeElement("a");
			return;
		}
		
		if($token[0]==="linkUrl"){
			$this->setAttribute($this->getAncestor("a"), "href", $token[1]);
			return;
		}
		
		if($token[0]==="linkTitle"){
			$this->setAttribute($this->getAncestor("a"), "title", $token[1]);
			return;
		}
		
		if($token[0]==="startImage"){
			$this->in_image = true;
			if(in_array("img", $this->open_elements)){
				// Ignore
				return;
			}
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			$this->appendElement("img");
			$this->state = "inImage";
			return;
		}
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected function inlineCode(){
		$token = $this->consume();
		
		if($token[0]==="character"){
			$this->appendText($token[1]);
			return;
		}
		
		if($token[0]==="closeCode"){
			$this->closeElement("code");
			$this->state = "inParagraph";
			return;
		}
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected $next_state = null;
	protected function data(){
		$token = $this->consume();
		if(!array_key_exists(1, $token)){
			$token[1] = null;
		}
		
		$increase_list_level = false;
		
		if($token[0]==="indent"){
			$last_element = $this->getLastElement();
			if(array_key_exists($last_element["name"], $this->indent_blocks)){
				$this->reopenLastElement();
				$this->state = $this->indent_blocks[$this->current["name"]];
				return;
			}
			unset($last_element);
			$next_token = $this->next();
			if($token[0]==="indent"&&($next_token[0]==="ul"||$next_token[0]==="ulParagraph")){
				$increase_list_level = true;
				$token = $this->consume();
			} else {
				// Ignore (placeholder for code block)
				return;
			}
			unset($next_token);
		}
		
		// If next state is set (from indent), then switch to that
		if($this->next_state){
			$this->state = $this->next_state;
			$this->next_state = null;
			$this->backup();
			return;
		}
		
		if($token[0]==="newBlock"){
			// Close all elements (may reenter them later)
			$this->closeBlocks();
			return;
		}
		
		if($token[0]==="newLine"){
			$this->appendElement("br");
			return;
		}
		
		if($token[0]==="startBlockquote"){
			$last_element = $this->getLastElement();
			if($last_element&&$last_element["name"]==="blockquote"){
				$this->reopenLastElement();
				return;
			}
			
			$this->openElement("blockquote");
			return;
		}
		
		if($token[0]==="endBlockquotes"){
			while(in_array("blockquote", $this->open_elements)){
				$this->closeElement("blockquote");
			}
			return;
		}
		
		if($token[0]==="rule"){
			// If in a paragraph, close it
			$closed_p = false;
			if(in_array("p", $this->open_elements)){
				$this->closeElement("p");
				$closed_p = true;
			}
			
			// Append <hr>
			$this->appendElement("hr");
			
			return;
		}
		
		if($token[0]==="ul"||$token[0]==="ulParagraph"){
			if(!in_array("ul", $this->open_elements)){
				$last_element = $this->getLastElement();
				if($last_element&&$last_element["name"]==="ul"){
					$this->reopenLastElement();
				} else {
					$this->openElement("ul");
				}
			}
			if($increase_list_level){
				if($this->current["name"]!=="li"){
					$last_element = $this->getLastElement();
					if($last_element&&$last_element["name"]==="li"){
						$this->reopenLastElement();
					} elseif($this->inBlock()){
						do {
							$this->closeBlock();
						} while($this->current["name"]!=="li"&&$this->inBlock());
					}
					if($this->current["name"]!=="li"){
						$this->openElement("ul");
						$this->openElement("li");
					}
				}
				$this->openElement("ul");
			} elseif(in_array("li", $this->open_elements)){
				$this->closeElement("li");
			}
			$this->openElement("li");
			if($token[0]==="ulParagraph"){
				$this->openElement("p");
			}
			return;
		}
		
		if($token[0]==="ol"||$token[0]==="olParagraph"){
			if(!in_array("ol", $this->open_elements)){
				$last_element = $this->getLastElement();
				if($last_element&&$last_element["name"]==="ol"){
					$this->reopenLastElement();
				} else {
					$this->openElement("ol");
				}
			}
			if(in_array("li", $this->open_elements)){
				$this->closeElement("li");
			}
			$this->openElement("li");
			if($token[0]==="olParagraph"){
				$this->openElement("p");
			}
			return;
		}
		
		if($token[0]==="atxHeader"){
			$this->openElement("h$token[1]");
			return;
		}
		
		if($token[0]==="startCode"){
			if(in_array("code", $this->open_elements)){
				// Ignore
				return;
			}
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			$this->openElement("code");
			return;
		}
		
		if($token[0]==="closeCode"){
			if(!in_array("code", $this->open_elements)){
				// Ignore
				return;
			}
			$this->closeElement("code");
			return;
		}
		
		if($token[0]==="startLink"){
			if(in_array("a", $this->open_elements)){
				// Ignore
				return;
			}
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			$this->openElement("a");
			return;
		}
		
		if($token[0]==="endLink"){
			if(!in_array("a", $this->open_elements)){
				// Ignore
				return;
			}
			$this->closeElement("a");
			return;
		}
		
		if($token[0]==="linkUrl"){
			$this->setAttribute($this->getAncestor("a"), "href", $token[1]);
			return;
		}
		
		if($token[0]==="linkTitle"){
			$this->setAttribute($this->getAncestor("a"), "title", $token[1]);
			return;
		}
		
		if($token[0]==="startImage"){
			$this->in_image = true;
			if(in_array("img", $this->open_elements)){
				// Ignore
				return;
			}
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			$this->appendElement("img");
			$this->state = "inImage";
			return;
		}
		
		if($token[0]==="startEm"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			if(in_array("em", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->openElement("em");
			return;
		}
		
		if($token[0]==="endEm"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			if(!in_array("em", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->closeElement("em");
			return;
		}
		
		if($token[0]==="toggleEm"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			if(!in_array("em", $this->open_elements)){
				$this->openElement("em");
				return;
			}
			$this->closeElement("em");
			return;
		}
		
		if($token[0]==="startStrong"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			if(in_array("strong", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->openElement("strong");
			return;
		}
		
		if($token[0]==="endStrong"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			if(!in_array("strong", $this->open_elements)){
				$this->appendText($token[1]);
				return;
			}
			$this->closeElement("strong");
			return;
		}
		
		if($token[0]==="toggleStrong"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			if(!in_array("strong", $this->open_elements)){
				$this->openElement("strong");
				return;
			}
			$this->closeElement("strong");
			return;
		}
		
		if($token[0]==="character"&&$token[1]===" "){
			$this->state = "afterSpace";
			return;
		}
		
		if($token[0]==="newline"){
			$this->state = "afterNewline";
			return;
		}
		
		if($token[0]==="character"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			$this->appendText($token[1]);
			return;
		}
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected function olContinue(){
		// Open <li> again
		$this->reopenLastElement();
		
		// Open a new <p>
		$this->openElement("p");
		
		$this->state = "data";
	}
	
	protected function ulContinue(){
		// Open <li> again
		$this->reopenLastElement();
		
		// Open a new <p>
		$this->openElement("p");
		
		$this->state = "data";
	}
	
	protected function newLine(){
		$token = $this->consume();
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected function inImage(){
		$token = $this->consume();
		
		if($token[0]==="imageAlt"){
			$this->setAttribute($this->current["children"][count($this->current["children"])-1], "alt", $token[1]);
			$this->state = "inImageUrl";
			return;
		}
		
		if($token[0]==="imageUrl"){
			$this->setAttribute($this->current["children"][count($this->current["children"])-1], "src", $token[1]);
			$this->state = "inImageTitle";
			return;
		}
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected function inImageUrl(){
		$token = $this->consume();
		
		if($token[0]==="imageUrl"){
			$this->setAttribute($this->current["children"][count($this->current["children"])-1], "src", $token[1]);
			$this->state = "inImageTitle";
			return;
		}
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected function inImageTitle(){
		$token = $this->consume();
		
		if($token[0]==="imageTitle"){
			$this->setAttribute($this->current["children"][count($this->current["children"])-1], "title", $token[1]);
			return;
		}
		
		if($token[0]==="endImage"){
			$this->state = "data";
			return;
		}
		
		throw(new LogicException("Invalid token type `$token[0]` and value `$token[1]`"));
	}
	
	protected function afterSpace(){
		while($next = $this->next()){
			if($next[0]!=="character"||$next[1]!==" "){
				break;
			}
			$this->consume();
		}
		$this->appendText(" ");
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
		$this->appendElement("br");
		$this->state = "data";
		return;
	}
}
