<?php

use bfrohs\markdown\Markdown as Markdown;

// Include array functions
require_once(MARKDOWN_SOURCE.'/app.php');

class MarkdownTest extends PHPUnit_Framework_TestCase {
	
	public function testParagraphs(){
		$text = "foo\nbar\n\nhello\n\n\nworld";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo bar</p><p>hello</p><p>world</p>', $markdown->toHTML());
	}
	
	public function testHorizontalRules(){
		$text = "foo\n\n---\n\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><hr><p>bar</p>', $markdown->toHTML());
	}
	
	public function testHorizontalRulesSpaces(){
		$text = "foo\n\n- -  -\n\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><hr><p>bar</p>', $markdown->toHTML());
	}
	
	public function testHorizontalRulesInvalid(){
		$text = "foo\n---\n\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo ---</p><p>bar</p>', $markdown->toHTML());
	}
	
	public function testHorizontalRulesInvalid2(){
		$text = "foo\n\n---\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><p>--- bar</p>', $markdown->toHTML());
	}
	
	public function testHorizontalRulesAsterisk(){
		$text = "foo\n\n***\n\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><hr><p>bar</p>', $markdown->toHTML());
	}
	
	public function testHorizontalRulesAsteriskSpaces(){
		$text = "foo\n\n* *  *\n\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><hr><p>bar</p>', $markdown->toHTML());
	}
	
	/**
	 * Converted to <strong><em>, then discarded when empty
	 */
	public function testHorizontalRulesAsteriskInvalid(){
		$text = "foo\n***\n\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo </p><p>bar</p>', $markdown->toHTML());
	}
	
	public function testHorizontalRulesAsteriskInvalid2(){
		$text = "foo\n\n***\nbar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><p><strong><em> bar</em></strong></p>', $markdown->toHTML());
	}
	
	public function testAtxHeaders1(){
		$text = "foo\n\n# bar\n\nhello";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><h1>bar</h1><p>hello</p>', $markdown->toHTML());
	}
	
	public function testAtxHeaders2(){
		$text = "foo\n\n## bar\n\nhello";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><h2>bar</h2><p>hello</p>', $markdown->toHTML());
	}
	
	public function testAtxHeaders3(){
		$text = "foo\n\n### bar\n\nhello";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><h3>bar</h3><p>hello</p>', $markdown->toHTML());
	}
	
	public function testAtxHeaders4(){
		$text = "foo\n\n#### bar\n\nhello";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><h4>bar</h4><p>hello</p>', $markdown->toHTML());
	}
	
	public function testAtxHeaders5(){
		$text = "foo\n\n##### bar\n\nhello";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><h5>bar</h5><p>hello</p>', $markdown->toHTML());
	}
	
	public function testAtxHeaders6(){
		$text = "foo\n\n###### bar\n\nhello";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><h6>bar</h6><p>hello</p>', $markdown->toHTML());
	}
	
	public function testAtxHeaders7(){
		$text = "foo\n\n####### bar\n\nhello";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo</p><h6># bar</h6><p>hello</p>', $markdown->toHTML());
	}
	
	public function testAtxHeadersEm(){
		$text = "# foo *bar  \nhello\n\nworld";
		$markdown = new Markdown($text);
		$this->assertEquals('<h1>foo <em>bar<br>hello</em></h1><p>world</p>', $markdown->toHTML());
	}
	
	public function testAtxHeadersStrong(){
		$text = "# foo **bar  \nhello\n\nworld";
		$markdown = new Markdown($text);
		$this->assertEquals('<h1>foo <strong>bar<br>hello</strong></h1><p>world</p>', $markdown->toHTML());
	}
	
	public function testList(){
		$text = "* foo\n* bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<ul><li>foo</li><li>bar</li></ul>', $markdown->toHTML());
	}
	
	public function testListPlus(){
		$text = "+ foo\n+ bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<ul><li>foo</li><li>bar</li></ul>', $markdown->toHTML());
	}
	
	public function testListDash(){
		$text = "- foo\n- bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<ul><li>foo</li><li>bar</li></ul>', $markdown->toHTML());
	}
	
	public function testCode(){
		$text = "`foo bar`";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><code>foo bar</code></p>', $markdown->toHTML());
	}
	
	public function testCodeMultiple(){
		$text = "``foo` bar``";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><code>foo` bar</code></p>', $markdown->toHTML());
	}
	
	public function testEmMultiple(){
		$text = "*foo* *bar*";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><em>foo</em> <em>bar</em></p>', $markdown->toHTML());
	}
	
	public function testNewLines(){
		$text = "foo\n bar \n hello  \n world";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo bar hello<br>world</p>', $markdown->toHTML());
	}
	
	public function testEm(){
		$text = "foo *bar*";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <em>bar</em></p>', $markdown->toHTML());
	}
	
	public function testEmWrapped(){
		$text = "foo *bar\nhello*";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <em>bar hello</em></p>', $markdown->toHTML());
	}
	
	public function testEmNewline(){
		$text = "foo *bar  \nhello*";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <em>bar<br>hello</em></p>', $markdown->toHTML());
	}
	
	public function testEmInWord(){
		$text = "foo*bar*";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo<em>bar</em></p>', $markdown->toHTML());
	}
	
	public function testEmUnderscore(){
		$text = "foo _bar_";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <em>bar</em></p>', $markdown->toHTML());
	}
	
	public function testEmUnderscoreInWord(){
		$text = "foo_bar_";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo<em>bar</em></p>', $markdown->toHTML());
	}
	
	public function testStrong(){
		$text = "foo **bar**";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <strong>bar</strong></p>', $markdown->toHTML());
	}
	
	public function testStrongInWord(){
		$text = "foo**bar**";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo<strong>bar</strong></p>', $markdown->toHTML());
	}
	
	public function testStrongUnderscore(){
		$text = "foo __bar__";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <strong>bar</strong></p>', $markdown->toHTML());
	}
	
	public function testStrongUnderscoreInWord(){
		$text = "foo__bar__";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo<strong>bar</strong></p>', $markdown->toHTML());
	}
	
	public function testEmStrong(){
		$text = "foo ***bar***";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <strong><em>bar</em></strong></p>', $markdown->toHTML());
	}
	
	public function testEmStrong2(){
		$text = "*foo **bar***";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><em>foo <strong>bar</strong></em></p>', $markdown->toHTML());
	}
	
	public function testEmStrongOverlapping(){
		$text = "*foo **bar* hello**";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><em>foo <strong>bar</strong></em><strong> hello</strong></p>', $markdown->toHTML());
	}
	
	public function testStrongEm(){
		$text = "**foo *bar***";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><strong>foo <em>bar</em></strong></p>', $markdown->toHTML());
	}
	
	public function testEmMultipleOpen(){
		$text = "*foo *bar*";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><em>foo bar</em></p>', $markdown->toHTML());
	}
	
	public function testEmUnclosed(){
		$text = "*foo bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><em>foo bar</em></p>', $markdown->toHTML());
	}
	
	public function testStrongMultipleOpen(){
		$text = "**foo **bar**";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><strong>foo bar</strong></p>', $markdown->toHTML());
	}
	
	public function testStrongUnclosed(){
		$text = "**foo bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p><strong>foo bar</strong></p>', $markdown->toHTML());
	}
	
	public function testBackslashAsterisk(){
		$text = "\*foo bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>*foo bar</p>', $markdown->toHTML());
	}
	
	public function testBackslashEm(){
		$text = "\**foo bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>*<em>foo bar</em></p>', $markdown->toHTML());
	}
	
	public function testBackslashStrong(){
		$text = "\***foo bar";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>*<strong>foo bar</strong></p>', $markdown->toHTML());
	}
}
