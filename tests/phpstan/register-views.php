<?php

/*
 * PHPStan bootstrap: register the package's view namespace so Larastan can
 * resolve `messenger::*` view strings (e.g. in Livewire render() methods) the
 * same way the framework does at runtime via the service provider's hasViews().
 * This teaches the analyser about a real namespace; it does not suppress errors.
 */

use Illuminate\Support\Facades\View;

if (function_exists('app') && app()->bound('view')) {
    View::addNamespace('messenger', __DIR__.'/../../resources/views');
}
