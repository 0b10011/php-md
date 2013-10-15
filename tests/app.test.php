<?php

// Include array functions
require_once(MARKDOWN_SOURCE.'/app.php');

class MarkdownTest extends PHPUnit_Framework_TestCase {
	
	public function testEm(){
		$text = 'foo *bar*';
		$markdown = new bfrohs\markdown\Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<p>foo <em>bar</em></p>');
	}
	
	public function testListAsterisk(){
		$text = '* foo *bar*';
		$markdown = new bfrohs\markdown\Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<ul><li><p>foo <em>bar</em></p></li></ul>');
	}
	
	public function testListAsteriskNoSpace(){
		$text = '*foo *bar*';
		$markdown = new bfrohs\markdown\Markdown($text);
		$this->assertEquals($markdown->toHTML(), '<p><em>foo *bar</em></p>');
	}
}
