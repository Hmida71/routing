<?php namespace Framework\Routing;

/**
 * Class Route.
 */
class Route
{
	/**
	 * @var Router
	 */
	protected Router $router;
	protected string $origin;
	protected string $path;
	/**
	 * @var \Closure|string
	 */
	protected $action;
	protected array $actionParams = [];
	protected ?string $name = null;
	protected array $options = [];

	/**
	 * Route constructor.
	 *
	 * @param Router          $router A Router instance
	 * @param string          $origin URL Origin. A string in the following format:
	 *                                {scheme}://{hostname}[:{port}]
	 * @param string          $path   URL Path. A string starting with '/'
	 * @param \Closure|string $action The action
	 */
	public function __construct(Router $router, string $origin, string $path, $action)
	{
		$this->router = $router;
		$this->setOrigin($origin);
		$this->setPath($path);
		$this->setAction($action);
	}

	/**
	 * Gets the URL Origin.
	 *
	 * @param mixed ...$params Parameters to fill the URL Origin placeholders
	 *
	 * @return string
	 */
	public function getOrigin(...$params) : string
	{
		if ($params) {
			return $this->router->fillPlaceholders($this->origin, ...$params);
		}
		return $this->origin;
	}

	protected function setOrigin(string $origin)
	{
		$this->origin = \ltrim($origin, '/');
		return $this;
	}

	/**
	 * Gets the URL.
	 *
	 * @param array $origin_params Parameters to fill the URL Origin placeholders
	 * @param array $path_params   Parameters to fill the URL Path placeholders
	 *
	 * @return string
	 */
	public function getURL(array $origin_params = [], array $path_params = []) : string
	{
		return $this->getOrigin(...$origin_params) . $this->getPath(...$path_params);
	}

	public function getOptions() : array
	{
		return $this->options;
	}

	public function setOptions(array $options)
	{
		$this->options = $options;
		return $this;
	}

	public function getName() : ?string
	{
		return $this->name;
	}

	public function setName(string $name)
	{
		$this->name = $name;
		return $this;
	}

	public function setPath(string $path)
	{
		$this->path = '/' . \trim($path, '/');
		return $this;
	}

	/**
	 * Gets the URL Path.
	 *
	 * @param mixed ...$params Parameters to fill the URL Path placeholders
	 *
	 * @return string
	 */
	public function getPath(...$params) : string
	{
		if ($params) {
			return $this->router->fillPlaceholders($this->path, ...$params);
		}
		return $this->path;
	}

	/**
	 * @return \Closure|string
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * Sets the Route Action.
	 *
	 * @param \Closure|string $action A \Closure or a string in the format of the __METHOD__
	 *                                constant. Example: App\Blog::show/0/2/1. Where /0/2/1 is the
	 *                                method parameters order
	 *
	 * @see setActionParams
	 * @see run
	 *
	 * @return $this
	 */
	public function setAction($action)
	{
		$this->action = \is_string($action) ? \trim($action, '\\') : $action;
		return $this;
	}

	public function getActionParams() : array
	{
		return $this->actionParams;
	}

	/**
	 * Sets the Action parameters.
	 *
	 * @param array $params The parameters. Note that the indexes set the order of how the
	 *                      parameters are passed to the Action
	 *
	 * @see setAction
	 *
	 * @return $this
	 */
	public function setActionParams(array $params)
	{
		\ksort($params);
		$this->actionParams = $params;
		return $this;
	}

	protected function checkResult($result) : void
	{
		if (\is_object($result)) {
			if (\method_exists($result, '__toString')) {
				return;
			}
		}
		if (\is_scalar($result) || $result === null) {
			return;
		}
		throw new \LogicException('Action return type must be scalar');
	}

	/**
	 * Run the Route Action.
	 *
	 * @param mixed ...$construct Class constructor parameters
	 *
	 * @return string The action returned value
	 */
	public function run(...$construct) : string
	{
		$action = $this->getAction();
		if ($action instanceof \Closure) {
			\ob_start();
			$result = $action($this->getActionParams(), ...$construct);
			$this->checkResult($result);
			$void = \ob_get_clean();
			return $void . $result;
		}
		if (\strpos($action, '::') === false) {
			$action .= '::' . $this->router->getDefaultRouteActionMethod();
		}
		[$classname, $action] = \explode('::', $action, 2);
		[$action, $params] = $this->extractActionAndParams($action);
		if ( ! \class_exists($classname)) {
			throw new Exception("Class not exists: {$classname}");
		}
		$class = new $classname(...$construct);
		if ( ! \method_exists($class, $action)) {
			throw new Exception(
				"Class method not exists: {$classname}::{$action}"
			);
		}
		if (\method_exists($class, 'beforeAction')) {
			\ob_start();
			$response = $class->beforeAction($action, $params);
			$response .= \ob_get_clean();
			if ($response !== '') {
				return $response;
			}
		}
		\ob_start();
		$result = $class->{$action}(...$params);
		$this->checkResult($result);
		if ($result === null && \method_exists($class, 'afterAction')) {
			$result = $class->afterAction($action, $params);
		}
		$void = \ob_get_clean();
		return $void . $result;
	}

	/**
	 * @param string $action An action part like: index/0/2/1
	 *
	 * @return array
	 */
	protected function extractActionAndParams(string $action) : array
	{
		if (\strpos($action, '/') === false) {
			return [$action, []];
		}
		$params = \explode('/', $action);
		$action = $params[0];
		unset($params[0]);
		if ($params) {
			$action_params = $this->getActionParams();
			$params = \array_values($params);
			foreach ($params as $index => $param) {
				if ( ! \array_key_exists($param, $action_params)) {
					throw new \InvalidArgumentException("Undefined action parameter: {$param}");
				}
				$params[$index] = $action_params[$param];
			}
		}
		return [
			$action,
			$params,
		];
	}
}
