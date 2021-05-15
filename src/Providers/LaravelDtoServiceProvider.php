<?php

namespace Cerbero\LaravelDto\Providers;

use Cerbero\Dto\Dto;
use Cerbero\Dto\Manipulators\ArrayConverter;
use Cerbero\LaravelDto\Manipulators\Listener;
use Cerbero\LaravelDto\Console\Commands\MakeDtoCommand;
use Cerbero\LaravelDto\Console\Manifest;
use Cerbero\LaravelDto\Console\DtoQualifierContract;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

use const Cerbero\Dto\IGNORE_UNKNOWN_PROPERTIES;

/**
 * The package service provider.
 *
 */
class LaravelDtoServiceProvider extends ServiceProvider
{
    /**
     * The package configuration path.
     *
     * @var string
     */
    protected const CONFIG = __DIR__ . '/../../config/dto.php';

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(MakeDtoCommand::class);
        }

        $this->publishes([static::CONFIG => $this->app->configPath('dto.php')], 'dto');

        ArrayConverter::instance()->setConversions($this->config('conversions'));

        Listener::instance()->listen($this->config('listeners'));

        if (class_exists($cloner = '\Symfony\Component\VarDumper\Cloner\VarCloner')) {
            $cloner::$defaultCasters[Dto::class] = function (Dto $dto) {
                return $dto->toArray();
            };
        }
    }

    /**
     * Retrieve the given configuration value
     *
     * @param string $key
     * @return mixed
     */
    protected function config(string $key)
    {
        return Config::get("dto.{$key}");
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(static::CONFIG, 'dto');

        $this->app->bind(DtoQualifierContract::class, $this->config('qualifier'));

        $this->app->singleton(Manifest::class, function () {
            $storagePath = Container::getInstance()->make('path.storage');
            return new Manifest($storagePath . '/cerbero_laravel_dto.php');
        });

        $this->app->resolving(Dto::class, function (Dto $dto) {
            return $dto->mutate(function (Dto $dto) {
                $dto->merge(Request::all(), IGNORE_UNKNOWN_PROPERTIES);
            });
        });
    }
}
