<?php namespace Tests\Routing;

use Framework\Routing\RouteAction;

class RouteActionMock extends RouteAction
{
	public function show($id)
	{
		return \func_get_args();
	}

	protected function foo()
	{
	}

	protected function beforeAction(string $action, array $params = [])
	{
		return \func_get_args();
	}

	protected function afterAction(string $action, array $params = [])
	{
		return \func_get_args();
	}
}