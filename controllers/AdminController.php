<?php

class AdminController extends AbstractController
{
    /**
    * @url POST /admin/regrequests/list
    */
    public function admin_regrequests_list($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_exception(403);
        }
        $filters = null;
        $limits = null;
        $order = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_exception(400, $e);
                }
            }

            if (property_exists($data, 'limits') && $data->limits) {
                $limits = $data->limits;
                try {
                    $this->_check_limits($limits);
                } catch (InternalException $e) {
                    $this->_handle_exception(400, $e);
                }
            }

            if (property_exists($data, 'order') && $data->order) {
                $order = $data->order;
            }
        }

        try {
            $db_data = DataBase::admin_regrequests_list($filters, $order, $limits);
        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        return $db_data;
    }
    
    /**
    * @url POST /admin/regrequests/count
    */
    public function admin_regrequests_count($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_exception(403);
        }
        $filters = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_exception(400, $e);
                }
            }
        }
        try {
            $db_data = DataBase::admin_regrequests_count($filters);
        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        return $db_data;
    }

    /**
    * @url POST /admin/regrequests/get
    */
    public function admin_regrequests_get($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_exception(403);
        }
        try {
            $check_fields = ['regrequest_id'];
            $check_int_fields = ['regrequest_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }

        try {
            $db_data = DataBase::admin_regrequests_get($data->regrequest_id);
            if ($db_data) {
                if ($db_data['is_approved'] === null) {
                    // для ожидающих заявок подбор лицевых счетов в соответствии с заявкой
                    $acc_data = DataBase::get_accounts_by_repr($db_data['acc_id'], null, 3);
                    $db_data['selected_accounts'] = $acc_data;

                } elseif ($db_data['is_approved']) {
                    // для утвержденных выдаем информацию о привязанном лс
                    $acc_data = DataBase::accounts_get($db_data['vk_user_id'], $db_data['linked_acc_id']);
                    $db_data['selected_accounts'] = [$acc_data];
                }
            }

        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }

        return $db_data;
    }
    
    /**
    * @url POST /admin/regrequests/aprove
    */
    public function admin_regrequests_aprove($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_exception(403);
        }

        try {
            $check_fields = ['regrequest_id', 'account_id'];
            $check_int_fields = ['regrequest_id', 'account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }

        try {
            $req_data = DataBase::admin_regrequests_get($data->regrequest_id);
            if (!$req_data) {
                //$this->_handle_exception(400, "registration request with id '$data->regrequest_id' not exists");
                return $this->_return_app_error(APPERR_REGREQUEST_NOT_EXISTS, $data->regrequest_id);
            }

            if ($req_data['is_approved'] !== null) {
                //$this->_handle_exception(400, "registration request with id '$data->regrequest_id' already processed");
                return $this->_return_app_error(APPERR_REGREQUEST_ALREADY_PROCESSED, $data->regrequest_id);
            }

            if (!DataBase::is_client_exists($data->account_id)) {
                //$this->_handle_exception(400, "account with id '$data->account_id' not exists");
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_EXISTS, $data->account_id);
            }

        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }

        try {
            DataBase::transaction_begin();

            DataBase::admin_users_add($req_data['vk_user_id'], 'USER', $this->_user_id);
            DataBase::admin_accounts_add($req_data['vk_user_id'], $data->account_id);
            DataBase::admin_regrequests_approve($req_data, $data->account_id, $this->_user_id);

            DataBase::transaction_commit();

        } catch (InternalException $e) {
            DataBase::transaction_rollback();
            $this->_handle_exception(500, $e);
        }
        return true;
    }
    
    /**
    * @url POST /admin/regrequests/reject
    */
    public function admin_regrequests_reject($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_exception(403);
        }

        try {
            $check_fields = ['regrequest_id'];
            $check_int_fields = ['regrequest_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }
        $rejection_reason = null;
        if (property_exists($data, 'rejection_reason')) {
            $rejection_reason = $data->rejection_reason;
        }

        try {
            $req_data = DataBase::admin_regrequests_get($data->regrequest_id);
            if (!$req_data) {
                //$this->_handle_exception(400, "registration request with id '$data->regrequest_id' not exists");
                return $this->_return_app_error(APPERR_REGREQUEST_NOT_EXISTS, $data->regrequest_id);
            }

            if ($req_data['is_approved'] !== null) {
                //$this->_handle_exception(400, "registration request with id '$data->regrequest_id' already processed");
                return $this->_return_app_error(APPERR_REGREQUEST_ALREADY_PROCESSED, $data->regrequest_id);
            }

            DataBase::admin_regrequests_reject($req_data, $this->_user_id, $rejection_reason);

        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        return true;
    }
    
    /**
    * @url POST /admin/data/set
    */
    public function admin_data_set($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_exception(403);
        }

        // check data
        try {
            $check_fields = ['clients', 'meters', 'clients_count', 'meters_count'];
            $check_int_fields = ['clients_count', 'meters_count'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);

            $clients = $data->clients;
            if (!is_array($clients)) throw new InternalException('bad clients data');
            
            $meters = $data->meters;
            if (!is_array($meters)) throw new InternalException('bad meters data');

            $clients_fields = ['acc_id', 'secret_code', 'acc_id_repr', 'tenant_repr', 'address_repr'];
            $check_int_fields = ['acc_id', 'secret_code'];
            $this->_check_fields($clients, $clients_fields, $check_int_fields, false);

            $meters_fields = ['index_num', 'acc_id', 'title', 'current_count'];
            $check_int_fields = ['index_num', 'acc_id', 'current_count'];
            $this->_check_fields($meters, $meters_fields, $check_int_fields, false);

        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }

        $result = [
            'clients_count' => 0,
            'meters_count' => 0
        ];

        try {
            DataBase::transaction_begin();

            // clear tables
            DataBase::clear_table('indications');
            DataBase::clear_table('meters');
            DataBase::clear_table('clients');

            $result['clients_count'] = DataBase::insert_data('clients', $clients_fields, $clients, 1000);

            $result['meters_count'] = DataBase::insert_data('meters', $meters_fields, $meters, 1000);

            DataBase::transaction_commit();

        } catch (InternalException $e) {
            DataBase::transaction_rollback();
            $this->_handle_exception(500, $e);
        }

        return $result;
    }
    
    /**
    * @url POST /admin/data/get
    */
    public function admin_data_get($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_exception(403);
        }
        $filters = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_exception(400, $e);
                }
            }
        }

        try {
            $db_data = DataBase::admin_indications_get($filters);

        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        return $db_data;
    }

    // ADMIN ONLY
    
    /**
    * @url POST /admin/apppermissions/set
    */
    public function admin_apppermissions_set($data) {
        if (!$this->_has_admin_privs()) {
            $this->_handle_exception(403);
        }

        // check data
        try {
            $check_fields = ['indications', 'registration'];
            $check_int_fields = ['indications', 'registration'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);

            $permissions = [];
            foreach ($check_int_fields as $perm) {
                $permissions[$perm] = (int)$data->$perm;
            }

            $date_begin = null;
            if (property_exists($data, 'date_begin')) {
                $date_begin = _str_to_date($data->date_begin);
            }
        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }

        try {
            $db_data = DataBase::app_permissions_set($permissions, $this->_user_id, $date_begin);

        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        return $db_data;
    }

    /**
    * @url POST /admin/users/list
    */
    public function admin_users_list($data) {
        if (!$this->_has_admin_privs()) {
            $this->_handle_exception(403);
        }
        $filters = null;
        $limits = null;
        $order = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_exception(400, $e);
                }
            }

            if (property_exists($data, 'limits') && $data->limits) {
                $limits = $data->limits;
                try {
                    $this->_check_limits($limits);
                } catch (InternalException $e) {
                    $this->_handle_exception(400, $e);
                }
            }

            if (property_exists($data, 'order') && $data->order) {
                $order = $data->order;
            }
        }

        try {
            $db_data = DataBase::admin_users_list($filters, $order, $limits);

        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        return $db_data;
    }

}
?>