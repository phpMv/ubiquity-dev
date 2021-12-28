\Ubiquity\security\csp\ContentSecurityManager::start(reportOnly: true,onNonce: function($name,$value){
    \Ubiquity\security\csp\ContentSecurityManager::defaultUbiquity()->addNonce($value,\Ubiquity\security\csp\CspDirectives::DEFAULT_SRC)->addHeaderToResponse(true);
});