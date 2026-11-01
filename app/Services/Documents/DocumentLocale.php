<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Illuminate\Support\Facades\App;

final readonly class DocumentLocale
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        $previous = App::getLocale();
        App::setLocale(config()->string('documents.locale'));

        try {
            return $callback();
        } finally {
            App::setLocale($previous);
        }
    }
}
