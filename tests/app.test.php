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
	
	public function testStrong(){
		$text = "foo **bar**";
		$markdown = new Markdown($text);
		$this->assertEquals('<p>foo <strong>bar</strong></p>', $markdown->toHTML());
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
}
