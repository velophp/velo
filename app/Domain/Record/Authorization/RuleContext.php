<?php

namespace App\Domain\Record\Authorization;

use Illuminate\Contracts\Auth\Authenticatable as Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final readonly class RuleContext
{
    private function __construct(
        public Auth|array|Collection|null $user = null,
        public array                      $body = [],
        public array                      $params = [],
        public array                      $query = [],
        public array                      $data = []
    ) {
    }

    public static function fromRequest(
        FormRequest|Request $request,
        array               $data = []
    ): self {
        return new self(
            user: $request->user(),
            body: $request->post(),
            params: $request->route()->parameters(),
            query: $request->query(),
            data: $data
        );
    }

    public static function make(
        Auth|array|Collection|null $user = null,
        array                      $body = [],
        array                      $params = [],
        array                      $query = [],
        array                      $data = []
    ): self {
        return new self(user: $user, body: $body, params: $params, query: $query, data: $data);
    }


    public static function fromUser(Auth|array|Collection $user): self
    {
        return new self(user: $user);
    }

    public static function empty(): self
    {
        return new self(user: null);
    }

    public function toArray(): array
    {
        return [
            'sys_request' => (object) [
                'auth'  => $this->user,
                'body'  => $this->body,
                'param' => $this->params,
                'query' => $this->query,
            ],
            ...$this->data,
        ];
    }
}
