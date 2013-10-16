<?php

use bfrohs\markdown\Markdown as Markdown;

// Include array functions
require_once(MARKDOWN_SOURCE.'/app.php');

class MarkdownTest extends PHPUnit_Framework_TestCase {
	
	public function testParagraphs(){
		$text = "foo\nbar\n\nhello\n\n\nworld";
		$markdown = new Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<p>foo bar</p><p>hello</p><p>world</p>');
	}
	
	public function testNewLines(){
		$text = "foo\n bar \n hello  \n world";
		$markdown = new Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<p>foo bar hello<br>world</p>');
	}
	
	public function testEm(){
		$text = "foo *bar*";
		$markdown = new Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<p>foo <em>bar</em></p>');
	}
	
	public function testStrong(){
		$text = "foo **bar**";
		$markdown = new Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<p>foo <strong>bar</strong></p>');
	}
	
	public function testEmStrong(){
		$text = "foo ***bar***";
		$markdown = new Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<p>foo <strong><em>bar</em></strong></p>');
	}
}
