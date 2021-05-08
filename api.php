<?php

/*
api version
*/
define('SERVER_API_VERSION', "1.0");

/*
data description
    db table
        db field                api field
*/
define('FIELDS_RESOLVE', array(
    "vk_users"              => array(
        "vk_user_id"        =>  "userID",
        "registration_date" =>  "registrationDate",
        "is_blocked"        =>  "userBlocked",
        "privileges"        =>  "userRole",
        "registered_by"     =>  "registratorID"
    ),
    "clients"               => array(
        "acc_id"            =>  "accountID",
        "secret_code"       =>  "registrationCode",
        "acc_id_repr"       =>  "account",
        "tenant_repr"       =>  "tenant",
        "address_repr"      =>  "address"
    ),
    "accounts"              => array(
        "id"                =>  "ID",
        "vk_user_id"        =>  "userID",
        "acc_id"            =>  "accountID"
    ),
    "registration_requests" => array(
        "id"                =>  "ID",
        "vk_user_id"        =>  "userID",
        "acc_id"            =>  "account",
        "surname"           =>  "surname",
        "first_name"        =>  "firstName",
        "patronymic"        =>  "patronymic",
        "street"            =>  "street",
        "n_dom"             =>  "house",
        "n_kv"              =>  "apartment",
        "request_date"      =>  "createDate",
        "update_date"       =>  "updateDate",
        "is_approved"       =>  "approved",
        "linked_acc_id"     =>  "accountID",
        "processed_by"      =>  "handlerID",
        "rejection_reason"  =>  "rejectionReason",
        "hide_in_app"       =>  "hidden",
        "del_in_app"        =>  "deleted"
    ),
    "meters"                => array(
        "id"                =>  "ID",
        "index_num"         =>  "codeAC",
        "acc_id"            =>  "accountID",
        "title"             =>  "nameAC",
        "current_count"     =>  "initialCount",
        "updated"           =>  "initialDate"
    ),
    "indications"            => array(
        "id"                =>  "ID",
        "meter_id"          =>  "meterID",
        "count"             =>  "count",
        "recieve_date"      =>  "date",
        "vk_user_id"        =>  "userID"
    ),
    "permitted_functions"   => array(
        "id"                =>  "ID",
        "date_begin"        =>  "beginDate",
        "vk_user_id"        =>  "userID",
        "indications"       =>  "indicationsAllowed",
        "registration"      =>  "registrationAllowed" 
    )
));

/*

*/
define('SERVER_API_URI', array(
    'admin' => array(
        'permissions' => array(
            'get',
            'set'
        ),
        'privilege' => array(
            'get',
            'set'
        ),
        'regrequests' => array(
            'list',
            'count',
            'get',
            'approve',
            'reject'
        ),
        'accounts' => array(
            'list'
        ),
        'meters' => array(
            'list'
        ),
        'indications' => array(
            'list',
            'add'
        )
    ),
    'user' => array(
        'permissions' => array(
            'get'
        ),
        'privilege' => array(
            'get'
        ),
        'regrequests' => array(
            'list',
            'add',
            'hide',
            'del'
        ),
        'accounts' => array(
            'list'
        ),
        'meters' => array(
            'list'
        ),
        'indications' => array(
            'list',
            'add'
        )
    )
));
?>