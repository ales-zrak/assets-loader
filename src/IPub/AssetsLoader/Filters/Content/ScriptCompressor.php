<?php
/**
 * ScriptCompressor.php
 *
 * Javascript compressor helper class, minifies javascript
 * Based on JSMin (http://code.google.com/p/jsmin-php, 2008 Ryan Grove <ryan@wonko.com>, MIT License)
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:AssetsLoader!
 * @subpackage	Filters
 * @since		5.0
 *
 * @date		08.06.13
 */

namespace IPub\AssetsLoader\Filters\Content;

use IPub;
use IPub\AssetsLoader;
use IPub\AssetsLoader\Compilers;
use IPub\AssetsLoader\Filters;

class ScriptCompressor implements IContentFilter, Filters\IFilter
{
	public $ORD_LF		= 10;
	public $ORD_SPACE	= 32;

	public $a			= '';
	public $b			= '';
	public $input		= '';
	public $inputIndex	= 0;
	public $inputLength	= 0;
	public $lookAhead	= NULL;
	public $output		= '';
	public $error		= FALSE;

	/**
	 * Minify a Javascript string
	 *
	 * @param string $code
	 * @param Compilers\Compiler $compiler
	 *
	 * @return string
	 */
	public function __invoke($code, Compilers\Compiler $compiler)
	{
		$this->input		= str_replace("\r\n", "\n", $code);
		$this->inputLength	= strlen($this->input);
		$this->a			= '';
		$this->b			= '';
		$this->inputIndex	= 0;
		$this->lookAhead	= NULL;
		$this->output		= '';
		$this->error		= FALSE;

		$minified = trim($this->min());

		return $this->error ? $code : $minified;
	}

	// -- Instance Methods ---------------------------------------------

	protected function action($d)
	{
		switch($d) {
			case 1:
				$this->output .= $this->a;

			case 2:
				$this->a = $this->b;

				if ($this->a === "'" || $this->a === '"') {
					for (;;) {
						$this->output .= $this->a;
						$this->a		 = $this->get();

						if ($this->a === $this->b) {
							break;
						}

						if (ord($this->a) <= $this->ORD_LF) {
							//Unterminated string literal.
							$this->error = TRUE;
							return;
						}

						if ($this->a === '\\') {
							$this->output .= $this->a;
							$this->a		 = $this->get();
						}
					}
				}

			case 3:
				$this->b = $this->next();

				if ($this->b === '/' && (
						$this->a === '(' || $this->a === ',' || $this->a === '=' ||
								$this->a === ':' || $this->a === '[' || $this->a === '!' ||
								$this->a === '&' || $this->a === '|' || $this->a === '?')) {

					$this->output .= $this->a . $this->b;

					for (;;) {
						$this->a = $this->get();

						if ($this->a === '/') {
							break;
						} elseif ($this->a === '\\') {
							$this->output .= $this->a;
							$this->a		 = $this->get();
						} elseif (ord($this->a) <= $this->ORD_LF) {
							//Unterminated regular expression literal.
							$this->error = TRUE;
							return;
						}

						$this->output .= $this->a;
					}

					$this->b = $this->next();
				}
		}
	}

	protected function get()
	{
		$c = $this->lookAhead;
		$this->lookAhead = NULL;

		if ($c === NULL) {
			if ($this->inputIndex < $this->inputLength) {
				$c = substr($this->input, $this->inputIndex, 1);
				$this->inputIndex += 1;
			} else {
				$c = NULL;
			}
		}

		if ($c === "\r") {
			return "\n";
		}

		if ($c === NULL || $c === "\n" || ord($c) >= $this->ORD_SPACE) {
			return $c;
		}

		return ' ';
	}

	protected function isAlphaNum($c)
	{
		return ord($c) > 126 || $c === '\\' || preg_match('/^[\w\$]$/', $c) === 1;
	}

	protected function min()
	{
		$this->a = "\n";
		$this->action(3);

		while ($this->a !== NULL && !$this->error) {
			switch ($this->a) {
				case ' ':
					if ($this->isAlphaNum($this->b)) {
						$this->action(1);
					} else {
						$this->action(2);
					}
					break;

				case "\n":
					switch ($this->b) {
						case '{':
						case '[':
						case '(':
						case '+':
						case '-':
							$this->action(1);
							break;

						case ' ':
							$this->action(3);
							break;

						default:
							if ($this->isAlphaNum($this->b)) {
								$this->action(1);
							}
							else {
								$this->action(2);
							}
					}
					break;

				default:
					switch ($this->b) {
						case ' ':
							if ($this->isAlphaNum($this->a)) {
								$this->action(1);
								break;
							}

							$this->action(3);
							break;

						case "\n":
							switch ($this->a) {
								case '}':
								case ']':
								case ')':
								case '+':
								case '-':
								case '"':
								case "'":
									$this->action(1);
									break;

								default:
									if ($this->isAlphaNum($this->a)) {
										$this->action(1);
									}
									else {
										$this->action(3);
									}
							}
							break;

						default:
							$this->action(1);
							break;
					}
			}
		}

		return $this->output;
	}

	protected function next()
	{
		$c = $this->get();

		if ($c === '/') {
			switch($this->peek()) {
				case '/':
					for (;;) {
						$c = $this->get();

						if (ord($c) <= $this->ORD_LF) {
							return $c;
						}
					}

				case '*':
					$this->get();

					for (;;) {
						switch($this->get()) {
							case '*':
								if ($this->peek() === '/') {
									$this->get();
									return ' ';
								}
								break;

							case NULL:
								//Unterminated comment.
								$this->error = TRUE;
								return;
						}
					}

				default:
					return $c;
			}
		}

		return $c;
	}

	protected function peek()
	{
		$this->lookAhead = $this->get();
		return $this->lookAhead;
	}
}