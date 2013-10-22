<?php

namespace bfrohs\markdown;

require_once($GLOBALS['MARKDOWN_SOURCE']."/exceptions.php");

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
	
	public function toHTML(){
		return $this->nodeToHTML($this->parse_tree);
	}
	
	protected function nodeToHTML($node = null){
		$html = "";
		
		$name = array_key_exists("name", $node) ? $node["name"] : null;
		
		$attributes = array_key_exists("attributes", $node) ? $node["attributes"] : null;
		
		$empty = array_key_exists("empty", $node) ? $node["empty"] : null;
		
		if($name!=="#ROOT"){
			$node_attributes = "";
			if($attributes){
				ksort($attributes);
				foreach($attributes as $attribute => $value){
					$node_attributes .= ' '.$attribute.'="'.htmlspecialchars($value).'"';
				}
			}
			$html .= "<$name$node_attributes>";
			unset($node_attributes);
		}
		
		if($empty){
			return $html;
		}
		
		foreach($node["children"] as $child){
			if($child["name"]==="#TEXT"){
				$html .= $this->escapeStringForHtml($child["value"]);
				continue;
			}
			
			$html .= $this->nodeToHTML($child);
		}
		
		if($name!=="#ROOT"){
			$html .= "</$name>";
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
	protected $state = "newBlock";
	protected $states = array(
		"newBlock" => "newBlock",
		"hardLine" => "hardLine",
		"softLine" => "softLine",
		"inLine" => "inLine",
		"afterSpace" => "afterSpace",
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
	
	/**
	 * Tracks whether we're currently in a list
	 * @var bool 
	 */
	protected $in_list = false;
	
	protected function newBlock(){
		$this->tokens[] = array("newBlock");
		$this->in_list = false;

		// Ignore extra leading whitespace
		do {
			$consumed = $this->consume("\n");
			$consumed += $this->consume(" ");
		} while($consumed);
		
		$this->state = "hardLine";
		return $this->hardLine(true);
	}
	
	protected function hardLine($new_block = false){
		$this->tokens[] = array("newLine");
		
		return $this->startLine($new_block);
	}
	
	protected function softLine(){
		return $this->startLine(false, false);
	}
	
	protected function startLine($new_block = false, $hard_break = true){
		// Ignore extra leading whitespace
		do {
			$consumed = $this->consume("\n");
			$consumed += $this->consume(" ");
		} while($consumed);
		
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
				$this->tokens[] = array("rule", $ch);
				$this->state = "newBlock";
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
			
			for($i=0;$i<$level;$i++){
				$this->tokens[] = array("startBlockquote");
			}
			
			$this->state = "newBlock";
			return;
		}
		
		$this->backup();
		$this->state = "inLine";
		
		// If a soft break and we've made it this far, add a space character
		if(!$hard_break){
			$this->tokens[] = array("character", " ");
		}
	}
	
	protected function inLine(){
		$ch = $this->consume();
		
		if($ch==="\n"){
			$this->consume(" "); // Trim leading whitespace
			if($this->consume("\n")){
				$this->state = "newBlock";
				return;
			}
			
			$this->state = "softLine";
			return;
		}
		
		if($ch===" "&&$this->consume(" ")&&$this->consume("\n")){
			$this->state = "hardLine";
			return;
		}
		
		if($ch==="\\"){
			$ch = $this->consume();
			$this->tokens[] = array("character", $ch);
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
			$this->tokens[] = array("character", $ch);
			$this->state = "afterSpace";
			return;
		}
		
		if($ch==="*"||$ch==="_"){
			$consumed = $this->consume($ch);
			if($consumed>2){
				$this->position = $this->position - ($consumed - 2);
				$consumed = 2;
			}
			if($consumed===2){
				$this->tokens[] = array("toggleEm");
				$this->tokens[] = array("toggleStrong");
				return;
			} elseif($consumed===1){
				$this->tokens[] = array("toggleStrong");
				return;
			}
			$this->tokens[] = array("toggleEm", $ch);
			return;
		}
		
		$this->tokens[] = array("character", $ch);
	}
	
	protected function afterSpace(){
		$ch = $this->consume();
		$this->state = "inLine";
		
		if($ch==="*"||$ch==="_"){
			$consumed = $this->consume($ch);
			if($consumed>2){
				$this->position = $this->position - ($consumed - 2);
				$consumed = 2;
			}
			if($consumed===2){
				$this->tokens[] = array("startEm");
				$this->tokens[] = array("startStrong");
				return;
			} elseif($consumed===1){
				$this->tokens[] = array("startStrong");
				return;
			}
			$this->tokens[] = array("startEm", $ch);
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
		
		$this->tokens[] = array("ul", "$ch");
		$this->state = "inLine";
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
		
		$this->tokens[] = array("ol", $number);
		$this->state = "inLine";
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
		
		$this->tokens[] = array("atxHeader", "$consumed");
		$this->state = "inLine";
	}
	
	protected $code_backticks = null;
	protected function startCode(){
		$consumed = $this->consume("`");
		
		if(!$consumed){
			throw(new BadMethodCallException("In code state, but no ` could be consumed"));
		}
		
		$this->code_backticks = $consumed;
		$this->state = "inCode";
		$this->tokens[] = array("startCode");
	}
	
	protected function inCode(){
		$consumed = $this->consume("`");
		
		if($consumed > $this->code_backticks){
			$this->backup($consumed - $this->code_backticks);
			$consumed = $this->code_backticks;
		}
		
		if($consumed===$this->code_backticks){
			$this->tokens[] = array("closeCode");
			return;
		}
		
		if($consumed) $this->backup($consumed);
		$ch = $this->consume();
		
		$this->tokens[] = array("character", $ch);
	}
	
	protected function startLink(){
		$ch = $this->consume();
		
		if($ch!=="["||!$this->match("[^\\]\\n]+\\]\\([^\"\\)\\n]*(\"([^\"\\\\]+|\\\\\\\\|\\\\.)+\")?\\)")){
			throw(new BadMethodCallException("In startLink state, but invalid pattern found"));
		}
		
		$this->state = "linkText";
		$this->tokens[] = array("startLink");
	}
	
	protected function linkText(){
		$ch = $this->consume();
		
		if($ch==="\\"){
			$ch = $this->consume();
			$this->tokens[] = array("character", $ch);
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
		
		$this->tokens[] = array("character", $ch);
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
			$this->tokens[] = array("linkUrl", $url.$ch);
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
			$this->tokens[] = array("linkUrl", $url.$ch);
			$this->state = "inLine";
			$this->tokens[] = array("endLink");
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
			$this->tokens[] = array("linkTitle", $title);
			$this->state = "inLine";
			$this->tokens[] = array("endLink");
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
		$this->tokens[] = array("startImage");
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
			if($alt!=='') $this->tokens[] = array("imageAlt", $alt);
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
			$this->tokens[] = array("imageUrl", $url.$ch);
			$this->state = "imageTitle";
			return;
		}
		
		if($this->match(" *\)")){
			$this->consume(" ");
			$this->consume(); // Consume )
			$this->tokens[] = array("imageUrl", $url.$ch);
			$this->state = "inLine";
			$this->tokens[] = array("endImage");
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
			$this->tokens[] = array("imageTitle", $title);
			$this->state = "inLine";
			$this->tokens[] = array("endImage");
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
	protected $state = "data";
	protected $states = array(
		"data" => "data",
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
	
	protected function appendText($text){
		$parent =& $this->current;
		$this->current["children"][] = array(
			"parent" => &$parent,
			"name" => "#TEXT",
			"type" => "text",
			"value" => $text,
		);
	}
	
	protected function data(){
		$token = $this->consume();
		if(!array_key_exists(1, $token)){
			$token[1] = null;
		}
		
		if($token[0]==="newBlock"){
			if(in_array("p", $this->open_elements)){
				$this->closeElement("p");
			}
			return;
		}
		
		if($token[0]==="newLine"){
			if(in_array("p", $this->open_elements)){
				$this->appendElement("br");
			}
			return;
		}
		
		if($token[0]==="startBlockquote"){
			
			// Get what level we should go to
			$level = 1;
			while(($next = $this->next())&&$next[0]==="startBlockquote"){
				$this->consume();
				$level++;
			}
			
			// Get the current level
			$current_level = 0;
			foreach($this->open_elements as $open_element){
				if($open_element==="blockquote"){
					$current_level++;
				}
			}
			
			// Figure out the difference
			if($current_level<$level){
				$difference = $level - $current_level;
				for($i=0;$i<$difference;$i++){
					$this->openElement("blockquote");
				}
			} else {
				$difference = $current_level - $level;
				for($i=0;$i<$difference;$i++){
					$this->closeElement("blockquote");
				}
			}
			
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
		
		if($token[0]==="ul"){
			if(!in_array("ul", $this->open_elements)){
				$this->openElement("ul");
			}
			if(in_array("li", $this->open_elements)){
				$this->closeElement("li");
			}
			$this->openElement("li");
			return;
		}
		
		if($token[0]==="ol"){
			if(!in_array("ol", $this->open_elements)){
				$this->openElement("ol");
			}
			if(in_array("li", $this->open_elements)){
				$this->closeElement("li");
			}
			$this->openElement("li");
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
			$this->appendElement("img");
			$this->state = "inImage";
			return;
		}
		
		if($token[0]==="startEm"){
			if(!$this->inBlock()){
				$this->openElement("p");
			}
			if(in_array("em", $this->open_elements)){
				// Ignore
				return;
			}
			$this->openElement("em");
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
				// Ignore
				return;
			}
			$this->openElement("strong");
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
