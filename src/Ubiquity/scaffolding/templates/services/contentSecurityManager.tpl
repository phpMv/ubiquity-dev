\Ubiquity\security\csp\ContentSecurityManager::start(reportOnly: true,onNonce: function($name,$value){
	if($name==='jsUtils') {
		\Ubiquity\security\csp\ContentSecurityManager::defaultUbiquityDebug()->addNonce($value, \Ubiquity\security\csp\CspDirectives::DEFAULT_SRC)->addHeaderToResponse(true);
	}
});