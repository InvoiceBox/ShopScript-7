<?php

return array(
    'invoicebox_participant_id' => array(
        'value'        => '',
        'title'        => 'Номер магазина',
        'description'  => 'Номер магазина в платежной системе Invoicebox.',
        'control_type' => waHtmlControl::INPUT,
    ),
	'invoicebox_participant_ident' => array(
        'value'        => '',
        'title'        => 'Региональный код магазина',
        'description'  => 'Региональный код магазина в платежной системе Invoicebox.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'invoicebox_api_key' => array(
        'value'       => '',
        'title'       => 'Ключ безопасности магазина',
        'description' => 'Ваш секретный ключ в системе Invoicebox, известный только вам.',
        'control_type' => waHtmlControl::INPUT,
    ),
	'testmode' => array(
        'value'       => '',
        'title'       => 'Тестовый режим',
        'description' => 'Если вы хотите использовать тестовый режим установите флажок',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
	
);