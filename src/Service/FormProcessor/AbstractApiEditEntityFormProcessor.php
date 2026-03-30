<?php

namespace Wexample\SymfonyApi\Service\FormProcessor;

use Symfony\Component\Form\FormInterface;
use Wexample\SymfonyForms\Form\Data\EntityEditFormData;

abstract class AbstractApiEditEntityFormProcessor extends AbstractApiEntityFormProcessor
{
    public function createForm(
        $data = null,
        array $options = []
    ): FormInterface {
        if ($data instanceof EntityEditFormData) {
            $entity = $this->getApiClient()
                ->getRepository($data->getEntityType())
                ->fetch($data->getSecureId());

            $data->setEntity($entity);

            $formData = $data->getFormData() ?? [];
            foreach ($this->getEditFieldNames() as $field) {
                $formData[$field] = $entity->$field ?? null;
            }

            $data->setFormData($formData);
        }

        return parent::createForm($data, $options);
    }

    protected function getEditFieldNames(): array
    {
        return [];
    }
}
