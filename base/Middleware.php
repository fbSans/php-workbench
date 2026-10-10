<?php

namespace Base;

interface Middleware {
    public function handle(Request $request, callable $next): Response;
}