<?php
/**
 * CssLoader.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:AssetsLoader!
 * @subpackage	Components
 * @since		5.0
 *
 * @date		08.06.13
 */

namespace IPub\AssetsLoader\Components;

use Nette;
use Nette\Utils;

use IPub;
use IPub\AssetsLoader\Exceptions;
use IPub\AssetsLoader\Files;

class CssLoader extends AssetsLoader
{
	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var string
	 */
	protected $contentType = 'text/css';

	/**
	 * @var bool
	 */
	protected $alternate = FALSE;

	/**
	 * Set title
	 *
	 * @param string $title
	 *
	 * @return CssLoader
	 */
	public function setTitle($title)
	{
		$this->title = $title;

		return $this;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * Set alternate
	 *
	 * @param bool $alternate
	 *
	 * @return CssLoader
	 */
	public function setAlternate($alternate)
	{
		$this->alternate = $alternate;

		return $this;
	}

	/**
	 * Is alternate ?
	 *
	 * @return bool
	 */
	public function isAlternate()
	{
		return $this->alternate;
	}

	/**
	 * Get link element
	 *
	 * @param string $source
	 * @param string $media
	 *
	 * @return Utils\Html
	 */
	public function getElement($source, $media = NULL)
	{
		return Utils\Html::el('link')
			->rel('stylesheet'. ($this->isAlternate() ? ' alternate' : ''))
			->type($this->contentType)
			->title($this->title)
			->media(!trim($media) ? 'all' : $media)
			->href($source);
	}

	/**
	 * @return void
	 */
	public function renderFiles()
	{
		// Remote files
		foreach ($this->files->getRemoteFiles() as $file) {
			echo $this->getElement($file), PHP_EOL;
		}

		$filesByMedia = [];

		foreach ($this->files as $file) {
			$filesByMedia[$file->getAttribute()][(string) $file] = $file;
		}

		foreach ($filesByMedia as $media => $files) {
			// Check if we should join all files into one
			if ($this->asset->getJoinFiles()) {
				// Compile files collection
				$result = $this->compiler->generate($files, $this->contentType);

				echo $this->getElement($this->getPresenter()->link(':IPub:AssetsLoader:assets', ['type' => 'css', 'id' => $result->hash, 'timestamp' => $result->lastModified]), $media), PHP_EOL;

			// Leave files splitted
			} else {
				foreach ($files as $file) {
					// Compile single file
					$result = $this->compiler->generate([$file], $this->contentType);

					echo $this->getElement($this->getPresenter()->link(':IPub:AssetsLoader:assets', ['type' => 'css', 'id' => $result->hash, 'timestamp' => $result->lastModified])), PHP_EOL;
				}
			}
		}
	}

	/**
	 * Generates link
	 *
	 * @return string
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function getLink()
	{
		$hasArgs = func_num_args() > 0;

		if ($hasArgs) {
			// Backup files
			$backup = $this->files;
			// Clear files collection
			$this->clear();

			// Get all arguments which could be files
			$args = func_get_args();
			$args = reset($args);

			// Create new collection from arguments
			$newFiles = new Files\FilesCollection;
			$newFiles->addFiles($args);

			// Create new files collection
			$this->setFiles($newFiles);
		}

		$filesByMedia = [];

		foreach ($this->files as $file) {
			$filesByMedia[$file->getAttribute()][(string) $file] = $file;
		}

		if (count($filesByMedia)>1) {
			throw new Exceptions\InvalidStateException("Can't generate link for combined media.");
		}

		if (!$this->asset->getJoinFiles()) {
			throw new Exceptions\InvalidStateException("Can't generate link with disabled joinFiles.");
		}

		// Compile files collection
		$result = $this->compiler->generate(reset($filesByMedia), $this->contentType);

		$link = $this->getPresenter()->link(':IPub:AssetsLoader:assets', ['type' => 'css', 'id' => $result->hash, 'timestamp' => $result->lastModified]);

		if ($hasArgs) {
			$this->setFiles($backup);
		}

		return $link;
	}
}