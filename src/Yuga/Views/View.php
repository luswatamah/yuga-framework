<?php
/**
 * @author Mahad Tech Solutions
 */
namespace Yuga\Views;

use Yuga\App;
use Yuga\Support\Str;

class View
{
	protected $viewFile;
	protected $viewEngine;

	/**
	 * Get the yuga-view-engine instance
	 * 
	 * @param string $view 
	 * @param array|null $data
	 */
	public function __construct($view = null, array $data = null)
	{
		$this->viewEngine = App::make('view');
		$view = $this->processViewPath($view);

		$this->viewFile = $view;

		if ($data) {
			$this->with($data);
		}
	}

	protected function processViewPath($path = null)
	{
		if ($path)
			return str_replace(".", "/", $path);
	}

	/**
	 * Render the view to the user
	 * 
	 * @param null
	 * 
	 * @return string
	 */
	public function __toString()
	{
		try {
			return $this->viewEngine->display($this->viewFile);
		} catch (\Throwable $e) {
			if ($this->processHaxRuntime() !== false)
				trigger_error('Exception in ' . __METHOD__ . "(): {$e->getMessage()} in {$e->getFile()}: {$e->getLine()} \n({$this->processHaxRuntime()}): {$e->getLine()}", E_USER_ERROR);
			else
				trigger_error('Exception in ' . __METHOD__ . "(): {$e->getMessage()} in {$e->getFile()}: {$e->getLine()}", E_USER_ERROR);
		}
	}

	protected function processHaxRuntime()
	{
		$root = str_replace("./", "", $this->viewEngine->getTemplateDirectory());

		$haxFile = str_replace("/", DIRECTORY_SEPARATOR, $root) . $this->viewFile . ".hax.php";
		if (file_exists($haxFile))
			return $haxFile;
		return false;
	}

	/**
	 * Pass data to the view and bind it to variables
	 * 
	 * @param array|[] $data
	 * 
	 * @return static
	 */
	public function with($data = null, $value = null)
	{
		if (is_array($data)) {
			foreach ($data as $var => $value) {
				$this->viewEngine->$var = $value;	
			}
		} else {
			$this->viewEngine->$data = $value;
		}
		return $this;
	}

	public function shares($key, $value)
    {
        return $this->with($key, $value);
    }

	/**
	 * Get the first view that exists in the array and render that instead
	 * 
	 * @param array|null
	 * 
	 * @return static
	 */
	public function first(array $views = null)
	{
		if ($views) {
			foreach ($views as $view) {
				if (file_exists(path('resources/views/' . $this->processViewPath($view). '.hax.php'))) {
					$this->viewFile = $view;
					break;
				} elseif (file_exists(path('resources/views/' . $this->processViewPath($view). '.php'))) {
					$this->viewFile = $view;
					break;
				}
			}
		}
		return $this;
	}

	public static function exists(string $view)
	{
		if (file_exists(path('resources/views/' . str_replace(".", "/", $view) . '.hax.php'))) {
			return true;
		} elseif (file_exists(path('resources/views/' . str_replace(".", "/", $view) . '.php'))) {
			return true;
		}

		return false;
	}

	public static function make(string $view)
	{
		return new static($view);
	}

	public function __call($method, $parameters)
	{
        if (preg_match('/^with(.+)$/', $method, $matches)) {
			$decamelized = Str::deCamelize($matches[1]);
			$camelized = Str::camelize($decamelized);
			return $this->with($camelized, $parameters[0]);
        }
		return call_user_func_array([$this->viewEngine, $method], $parameters);
	}
}