<?php


namespace Base;

class Response {
       public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = []
    ) {}

    public function send()
    {
        http_response_code($this->status);
        foreach($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->content;
    }

    public static function redirect(string $url, int $status = 302): static
    {
        return new static('', $status, [
            'Location' => $url,
        ]);
    }

    public static function text(string $content, int $status): static 
    {
        return new static($content, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public static function json(mixed $content, int $status): static 
    {
        return new static (json_encode($content), $status, [
            'Content-Type' => 'application/json'
        ]);
    }

    public static function html(mixed $content, int $status)
    {
        return new static($content, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}