<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Ibexa\Admin;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\AdminUi\Menu\MainMenuBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuListener implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMenuEvent::MAIN_MENU => ['onMenuConfigure', 0],
        ];
    }

    public function onMenuConfigure(ConfigureMenuEvent $event): void
    {
        if (!$this->authorizationChecker->isGranted('ibexa:import_export:access')) {
            return;
        }

        $menu = $event->getMenu();

        if (!isset($menu[MainMenuBuilder::ITEM_ADMIN])) {
            return;
        }

        $menu[MainMenuBuilder::ITEM_ADMIN]
            ->addChild('import_export', ['route' => 'netgen_import_export.route.admin.index'])
            ->setLabel('netgen.ibexa_import_export.title')
            ->setExtra('translation_domain', 'import_export');
    }
}
