<?php 

namespace OData\OData;

use League\Fractal\TransformerAbstract;
use Illuminate\Database\Eloquent\Model;
use OData\OData\Serializers\NoDataSerializer;

trait ODataTransform
{
	use ODataModel;

	protected function odataJsonResponse(Model $model)
    {
        $this->setParams($_GET);
        $this->setInstance($model);

        if ($this->isRequestFile())
            return $this->getFile();

        return $this->getJsonResponse($this->getCollection());
    }

    private function getJsonResponse($data)
    {
    	if (!$this->transformer)
    		return $this->defaultResponse($this->serializeData($data, 'model'));

    	$this->transformer = new $this->transformer;

    	if ($this->transformer instanceof TransformerAbstract)
            $collection = $this->getFractalTransformData($data);
        else
        	$collection = $this->getDefaultTransformData($data);

        return $this->defaultResponse($collection);
    }

    private function getFractalTransformData($data)
    {
    	if (isset($_GET["first"]))
        	$data = $data->first();

        $transformation = fractal($data, $this->transformer)
            ->serializeWith(new NoDataSerializer());

        if (isset($_GET['include']))
            $transformation->parseIncludes($_GET['include']);

        return $transformation->toArray();
    }

    private function getDefaultTransformData($data)
    {
    	if (method_exists($this->transformer, 'transform'))
    		return $this->serializeData($data, 'class');
    	else
    		return $this->serializeData($data, 'model');
    }

    private function serializeData($data, $type = 'class')
    {
    	$collection = [];

    	foreach ($data as $item) {
    		if ($type == 'model'){
    			if (method_exists($this->model, 'transform'))
    				$collection[] = $item->transform();
    			else
    			    $collection[] = $item;
    		}
    		else
    		    $collection[] = $this->transformer->transform($item);
    	}

    	return ['data' => $collection];
    }

    private function defaultResponse($data)
	{
		$collection = $this->setDefaultProperties($data);

		if (method_exists($this, 'successResponse'))
			return $this->successResponse($collection, 200);

		return response()->json(['data' => $collection, 'code' => 200], 200);
	}
}