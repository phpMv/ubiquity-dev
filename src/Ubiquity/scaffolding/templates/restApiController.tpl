<?php
%namespace%

%uses%
use Ubiquity\controllers\rest\api\jsonapi\JsonApiResponseFormatter;
use Ubiquity\controllers\rest\ResponseFormatter;

%restAnnot%
%route%
class %controllerName% extends %baseClass% {
	protected function getResponseFormatter(): ResponseFormatter {
		return new JsonApiResponseFormatter('%routePath%');
	}
}
