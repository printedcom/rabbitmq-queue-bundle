<?php

namespace Printed\Bundle\Queue\Common\Traits;

/**
 * Class GetDataItemFromDataOrThrowTrait
 *
 * Get value from a hashmap by key or throw.
 */
trait GetDataItemFromDataOrThrowTrait
{

    /**
     * @param array $data
     * @param string $dataKey
     *
     * @return mixed
     */
    protected function getDataItemFromDataOrThrow(array $data, string $dataKey)
    {
        if (!array_key_exists($dataKey, $data)) {
            throw new \RuntimeException(sprintf(
                "Couldn't find data item by key `%s` for class `%s`",
                $dataKey,
                get_called_class()
            ));
        }

        return $data[$dataKey];
    }

    /**
     * @param array $data
     * @param string $dataKey
     *
     * @return mixed|null
     */
    protected function getDataItemFromData(array $data, string $dataKey)
    {
        return array_key_exists($dataKey, $data) ? $data[$dataKey] : null;
    }

}
