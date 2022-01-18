<?php 

namespace OData\OData\Serializers;

use League\Fractal\Serializer\DataArraySerializer;

class NoDataSerializer extends DataArraySerializer
{
    public function mergeIncludes($transformedData, $includedData)
    {
        $includedData = array_map(function ($include) {
            return $include['data'];
        }, $includedData);

        return parent::mergeIncludes($transformedData, $includedData);
    }
}