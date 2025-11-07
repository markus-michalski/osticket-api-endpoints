<?php

return array(
    'id' =>             'com.osticket:api-endpoints',
    'version' =>        '1.2.0',
    'name' =>           'API Endpoints',
    'author' =>         'Markus Michalski',
    'description' =>    'Extends osTicket API with additional endpoints and parameters: ticket creation with Markdown support, department selection, and subticket functionality.',
    'url' =>            'https://github.com/markus-michalski/osticket-plugins/tree/main/api-endpoints',
    'plugin' =>         'class.ApiEndpointsPlugin.php:ApiEndpointsPlugin',
    'config' =>         'config.php:ApiEndpointsConfig'
);
