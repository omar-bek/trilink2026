<?php

namespace App\Services\Shipping;

/**
 * Builds carrier adapters by code. Pulls per-carrier credentials from
 * config/services.php — see config/services.php "carriers" key.
 *
 * Adding a new carrier means dropping a class in this folder and registering
 * the code below; nothing else in the platform needs to change.
 */
class CarrierFactory
{
    private const REGISTRY = [
        'aramex' => AramexCarrier::class,
        'dhl' => DhlCarrier::class,
        'fedex' => FedExCarrier::class,
        'ups' => UpsCarrier::class,
        'fetchr' => FetchrCarrier::class,
    ];

    /**
     * @return array<int, string>
     */
    public function availableCodes(): array
    {
        return array_keys(self::REGISTRY);
    }

    public function make(string $code): CarrierInterface
    {
        if (! isset(self::REGISTRY[$code])) {
            throw new \InvalidArgumentException("Unknown carrier: {$code}");
        }
        $class = self::REGISTRY[$code];
        $config = config("services.carriers.{$code}", []);

        return new $class($config);
    }

    /**
     * @return array<int, CarrierInterface>
     */
    public function all(): array
    {
        return array_map(fn ($code) => $this->make($code), $this->availableCodes());
    }
}
