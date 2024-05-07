<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Form;

use Netgen\ContentBrowser\Form\Type\ContentBrowserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'package',
                FileType::class,
                [
                    'required' => true,
                ],
            )->add(
                'parent_location',
                ContentBrowserType::class,
                [
                    'item_type' => 'ibexa_location',
                    'required' => true,
                ],
            );
    }
}
