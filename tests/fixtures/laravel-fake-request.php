<?php
 
/* @HINT: fake of Illuminate\Http\Request::create('/test-route') */
 
use LaravelFaked\Http\Lifecycle\FakeRequest;
 
return FakeRequest::create('/test-route');

?>
