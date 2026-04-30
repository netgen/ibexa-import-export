<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Form;

use Netgen\ContentBrowser\Form\Type\ContentBrowserMultipleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExportType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('translation_domain', 'import_export');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('migration_type', ChoiceType::class, [
            'choices' => [
                'Create' => 'create',
                'Update' => 'update',
            ],
            'required' => true,
            'expanded' => true,
            'multiple' => false,
            'label' => 'netgen.ibexa_import_export.form.export.migration_type',
        ])
        ->add('source_structure', ChoiceType::class, [
            'choices' => [
                'Content' => 'content',
                'Subtree' => 'subtree',
            ],
            'required' => true,
            'expanded' => true,
            'multiple' => false,
            'label' => 'netgen.ibexa_import_export.form.export.source_structure',
        ])->add(
            'source',
            ContentBrowserMultipleType::class,
            [
                'label' => 'netgen.ibexa_import_export.form.export.source',
                'item_type' => 'ibexa_content',
                'required' => true,
                'min' => 1,
                'max' => null,
            ],
        );
    }
}
