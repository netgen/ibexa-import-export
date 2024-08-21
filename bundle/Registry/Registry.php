<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Registry;

use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;
use OutOfBoundsException;

use function sprintf;

final class Registry
{
    /**
     * @var AbstractFieldHandler[]
     */
    private array $handlerMap = [];

    /**
     * @param AbstractFieldHandler[] $handlerMap
     */
    public function __construct(array $handlerMap = [])
    {
        foreach ($handlerMap as $identifier => $handler) {
            $this->register($identifier, $handler);
        }
    }

    public function register(string $identifier, AbstractFieldHandler $handler): void
    {
        $this->handlerMap[$identifier] = $handler;
    }

    /**
     * @throws OutOfBoundsException
     */
    public function get(?string $identifier): AbstractFieldHandler
    {
        return $this->handlerMap[$identifier] ?? throw new OutOfBoundsException(
            sprintf(
                "No handler is registered for identifier '%s'",
                $identifier,
            ),
        );
    }
}
