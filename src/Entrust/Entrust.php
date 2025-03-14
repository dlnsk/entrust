<?php namespace Zizaco\Entrust;

use Closure;
use Illuminate\Support\Facades\Facade;

class Entrust
{
    /**
     * Laravel application
     *
     * @var \Illuminate\Foundation\Application
     */
    public $app;

    /**
     * Create a new confide instance.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Checks if the current user has a Role by its name
     *
     * @param string $name Role name.
     *
     * @return bool
     */
    public function hasRole($permission)
    {
        if ($user = $this->user()) {
            return $user->hasRole($permission);
        }

        return false;
    }

    /**
     * Check if the current user has a permission by its name
     *
     * If the Permission model have a method with permission name (camelcase)
     * this method will call and his result will a result of
     * permission check.
     * If you use $params and it's not null the method should have a one parameter.
     *
     * @param string $permission Permission string.
     * @param string $params     (optional) Parameters used when permission checking.
     *
     * @return bool
     */
    public function can($permission, $params = null)
    {
        if ($user = $this->user()) {
            return $user->can( $permission, $params );
        }

        return false;
    }

    /**
     * Checks role(s) and permission(s) for thr current user.
     *
     * @param string|array $roles       Array of roles or comma separated string
     * @param string|array $permissions Array of permissions or comma separated string.
     * @param array        $options     validate_all (true|false) and
     *                                  return_type (boolean|array|both) and
     *                                  parameters for checking permissions (key is permission name)
     *
     * @throws \InvalidArgumentException
     *
     * @return array|bool
     */
    public function ability($roles, $permissions, $options = array())
    {
        if ($user = $this->user()) {
            return $user->ability($roles, $permissions, $options);
        }

        return false;
    }

    /**
     * Get the currently authenticated user or null.
     *
     * @return Illuminate\Auth\UserInterface|null
     */
    public function user()
    {
        return $this->app->auth->user();
    }

    /**
     * Filters a route for the name Role.
     *
     * If the third parameter is null then return 403.
     * Overwise the $result is returned.
     *
     * @param string       $route      Route pattern. i.e: "admin/*"
     * @param array|string $roles      The role(s) needed.
     * @param mixed        $result     i.e: Redirect::to('/')
     * @param bool         $cumulative Must have all roles.
     *
     * @return mixed
     */
    public function routeNeedsRole($route, $roles, $result = null, $cumulative=true)
    {
        if (!is_array($roles)) {
            $roles = mb_split('[\s,]+', $roles);
        }

        $filter_name = implode('_',$roles).'_'.substr(md5($route),0,6);

        if (!$result instanceof Closure) {
            $result = function () use ($roles, $result, $cumulative) {
                $hasARole = array();
                foreach ($roles as $role) {
                    if ($this->hasRole($role)) {
                        $hasARole[] = true;
                    } else {
                        $hasARole[] = false;
                    }
                }

                // Check to see if it is false and then
                // check additive flag and that the array only contains false.
                if (in_array(false, $hasARole) && ($cumulative || count(array_unique($hasARole)) == 1) ) {
                    if(! $result)
                        Facade::getFacadeApplication()->abort(403);

                    return $result;
                }
            };
        }

        // Same as Route::filter, registers a new filter
        $this->app->router->filter($filter_name, $result);

        // Same as Route::when, assigns a route pattern to the
        // previously created filter.
        $this->app->router->when( $route, $filter_name );
    }

    /**
     * Filters a route for the permission.
     *
     * If the third parameter is null then return 403.
     * Overwise the $result is returned.
     *
     * @param string       $route       Route pattern. i.e: "admin/*"
     * @param array|string $permissions The permission needed.
     * @param mixed        $result      i.e: Redirect::to('/')
     * @param bool         $cumulative  Must have all permissions
     *
     * @return mixed
     */
    public function routeNeedsPermission($route, $permissions, $result = null, $cumulative=true)
    {
        if (!is_array($permissions)) {
            $permissions = mb_split('[\s,]+', $permissions);
        }

        $filter_name = implode('_',$permissions).'_'.substr(md5($route),0,6);

        if (!$result instanceof Closure) {

            $result = function () use ($permissions, $result, $cumulative) {
                $hasAPermission = array();
                foreach ($permissions as $permission) {
                    if ($this->can($permission)) {
                        $hasAPermission[] = true;
                    } else {
                        $hasAPermission[] = false;
                    }
                }

                // Check to see if it is false and then
                // check additive flag and that the array only contains false.
                if (in_array(false, $hasAPermission) && ($cumulative || count(array_unique($hasAPermission)) == 1) ) {
                    if(! $result)
                        Facade::getFacadeApplication()->abort(403);

                    return $result;
                }
            };
        }

        // Same as Route::filter, registers a new filter
        $this->app->router->filter($filter_name, $result);

        // Same as Route::when, assigns a route pattern to the
        // previously created filter.
        $this->app->router->when( $route, $filter_name );
    }

    /**
     * Filters a route for the permission.
     *
     * If the third parameter is null then return 403.
     * Overwise the $result is returned.
     *
     * @param string       $route       Route pattern. i.e: "admin/*"
     * @param array|string $roles       The role(s) needed.
     * @param array|string $permissions The permission needed.
     * @param mixed        $result      i.e: Redirect::to('/')
     * @param bool         $cumulative  Must have all permissions
     *
     * @return void
     */
    public function routeNeedsRoleOrPermission($route, $roles, $permissions, $result = null, $cumulative=false)
    {
        if (!is_array($roles)) {
            $roles = mb_split('[\s,]+', $roles);
        }
        if (!is_array($permissions)) {
            $permissions = mb_split('[\s,]+', $permissions);
        }

        $filter_name = implode('_',$roles).'_'.implode('_',$permissions).'_'.substr(md5($route),0,6);

        if (!$result instanceof Closure) {

            $result = function () use ($roles, $permissions, $result, $cumulative) {
                $hasARole = array();
                foreach ($roles as $role) {
                    if ($this->hasRole($role)) {
                        $hasARole[] = true;
                    } else {
                        $hasARole[] = false;
                    }
                }

                $hasAPermission = array();
                foreach ($permissions as $permission) {
                    if ($this->can($permission)) {
                        $hasAPermission[] = true;
                    } else {
                        $hasAPermission[] = false;
                    }
                }
                // Check to see if it is false and then
                // check additive flag and that the array only contains false.
                if (((in_array(false, $hasARole) || in_array(false, $hasAPermission))) && ($cumulative || count(array_unique(array_merge($hasARole, $hasAPermission))) == 1 )) {
                    if(! $result)
                        Facade::getFacadeApplication()->abort(403);

                    return $result;
                }
            };
        }

        // Same as Route::filter, registers a new filter
        $this->app->router->filter($filter_name, $result);

        // Same as Route::when, assigns a route pattern to the
        // previously created filter.
        $this->app->router->when( $route, $filter_name );
    }
}
