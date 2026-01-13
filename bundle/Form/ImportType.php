<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Form;

use Netgen\ContentBrowser\Form\Type\ContentBrowserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ImportType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('translation_domain', 'import_export');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'package',
                FileType::class,
                [
                    'required' => true,
                    'label' => 'netgen.ibexa_import_export.form.import.package',
                ],
            )->add(
                'parent_location',
                ContentBrowserType::class,
                [
                    'item_type' => 'ibexa_location',
                    'required' => true,
                    'label' => 'netgen.ibexa_import_export.form.import.parent_location',
                ],
            );
    }
}
