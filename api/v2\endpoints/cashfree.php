<?php
if (empty($_POST['request']))
{
    $error_code = 4;
    $error_message = 'request can not be empty';
}
else
{
    if ($_POST['request'] == 'initialize')
    {
        if (!empty($_POST['phone']) && !empty($_POST['name']) && !empty($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) && !empty($_POST['amount']) && is_numeric($_POST['amount'])) {


            $order_id = uniqid().rand(100,1000);
            $customer_id = uniqid().rand(100,1000);
            $name = Wo_Secure($_POST['name']);
            $email = Wo_Secure($_POST['email']);
            $phone = Wo_Secure($_POST['phone']);
            $order_amount = Wo_Secure($_POST['amount']);

            $secretKey = $wo['config']['cashfree_secret_key'];
            $cashfree_client_key = $wo['config']['cashfree_client_key'];
            $callback_url = $wo['config']['site_url'] . "/requests.php?f=cashfree&s=wallet&amount=".$order_amount."&user_id=".$wo['user']['user_id']."&order_id=".$order_id."&customer_id=".$customer_id;


            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://sandbox.cashfree.com/pg/orders',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS =>'
            {
              "customer_details": {
                "customer_id": "'.$customer_id.'",
                "customer_email": "'.$email.'",
                "customer_phone": "'.$phone.'"
              },
              "order_id": "'.$order_id.'",
              "order_amount": '.$order_amount.',
              "order_currency": "INR",
              "order_meta": {
                "return_url": "'.$callback_url.'"
              }
            }
            ',
              CURLOPT_HTTPHEADER => array(
                'accept: application/json',
                'content-type: application/json',
                'x-api-version: 2023-08-01',
                'x-client-id: ' . $wo['config']['cashfree_client_key'],
                'x-client-secret: ' . $wo['config']['cashfree_secret_key']
              ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            if (!empty($response)) {
                $response = json_decode($response);

                if (!empty($response->message)) {
                    $error_code = 7;
                    $error_message = $response->message;
                }
                else{
                    $response_data = array(
                        'api_status' => 200,
                        'payment_session_id' => $response->payment_session_id,
                    );
                }
            }
            else{
                $error_code = 6;
                $error_message = "Something went wrong";
            }
        }
        else{
            $error_code = 5;
            $error_message = "phone , name , email , amount can not be empty";
        }
    }

    if ($_POST['request'] == 'upgrade')
    {
        if (empty($_POST['txStatus']) || $_POST['txStatus'] != 'SUCCESS')
        {
            $response_data = array(
                'api_status' => '400',
                'errors' => array(
                    'error_id' => 6,
                    'error_text' => 'txStatus can not be empty or txStatus != SUCCESS'
                )
            );
            echo json_encode($response_data, JSON_PRETTY_PRINT);
            exit();
        }
        $is_pro = 0;
        $stop = 0;
        $user = Wo_UserData($wo['user']['user_id']);
        if ($user['is_pro'] == 1)
        {
            $stop = 1;
            if ($user['pro_type'] == 1)
            {
                $time_ = time() - $star_package_duration;
                if ($user['pro_time'] > $time_)
                {
                    $stop = 1;
                }
            }
            else if ($user['pro_type'] == 2)
            {
                $time_ = time() - $hot_package_duration;
                if ($user['pro_time'] > $time_)
                {
                    $stop = 1;
                }
            }
            else if ($user['pro_type'] == 3)
            {
                $time_ = time() - $ultima_package_duration;
                if ($user['pro_time'] > $time_)
                {
                    $stop = 1;
                }
            }
            else if ($user['pro_type'] == 4)
            {
                if ($vip_package_duration > 0)
                {
                    $time_ = time() - $vip_package_duration;
                    if ($user['pro_time'] > $time_)
                    {
                        $stop = 1;
                    }
                }
            }
        }
        if ($stop == 0)
        {
            $pro_types_array = array(
                1,
                2,
                3,
                4
            );
            $pro_type = 0;
            if (!isset($_POST['pro_type']) || !in_array($_POST['pro_type'], $pro_types_array))
            {
                $response_data = array(
                    'api_status' => '400',
                    'errors' => array(
                        'error_id' => 7,
                        'error_text' => 'pro_type can not be empty'
                    )
                );
                echo json_encode($response_data, JSON_PRETTY_PRINT);
                exit();
            }
            $pro_type = $_POST['pro_type'];
            $orderId = $_POST["orderId"];
            $orderAmount = $_POST["orderAmount"];
            $referenceId = $_POST["referenceId"];
            $txStatus = $_POST["txStatus"];
            $paymentMode = $_POST["paymentMode"];
            $txMsg = $_POST["txMsg"];
            $txTime = $_POST["txTime"];
            $signature = $_POST["signature"];
            $data = $orderId . $orderAmount . $referenceId . $txStatus . $paymentMode . $txMsg . $txTime;
            $hash_hmac = hash_hmac('sha256', $data, $wo['config']['cashfree_secret_key'], true);
            $computedSignature = base64_encode($hash_hmac);
            if ($signature == $computedSignature)
            {
                $is_pro = 1;
            }
            else
            {
                $response_data = array(
                    'api_status' => '400',
                    'errors' => array(
                        'error_id' => 8,
                        'error_text' => 'something went wrong'
                    )
                );
                echo json_encode($response_data, JSON_PRETTY_PRINT);
                exit();
            }

        }
        if ($stop == 0)
        {
            $time = time();
            if ($is_pro == 1)
            {
                $update_array = array(
                    'is_pro' => 1,
                    'pro_time' => time() ,
                    'pro_' => 1,
                    'pro_type' => $pro_type
                );
                if (in_array($pro_type, array_keys($wo['pro_packages_types'])) && $wo['pro_packages'][$wo['pro_packages_types'][$pro_type]]['verified_badge'] == 1)
                {
                    $update_array['verified'] = 1;
                }
                $mysqli = Wo_UpdateUserData($wo['user']['user_id'], $update_array);
                global $sqlConnect;
                $amount1 = 0;
                if ($pro_type == 1)
                {
                    $img = $wo['lang']['star'];
                    $amount1 = $wo['pro_packages']['star']['price'];
                }
                else if ($pro_type == 2)
                {
                    $img = $wo['lang']['hot'];
                    $amount1 = $wo['pro_packages']['hot']['price'];
                }
                else if ($pro_type == 3)
                {
                    $img = $wo['lang']['ultima'];
                    $amount1 = $wo['pro_packages']['ultima']['price'];
                }
                else if ($pro_type == 4)
                {
                    $img = $wo['lang']['vip'];
                    $amount1 = $wo['pro_packages']['vip']['price'];
                }
                $notes = $wo['lang']['upgrade_to_pro'] . " " . $img . " : Cashfree";
                $create_payment_log = mysqli_query($sqlConnect, "INSERT INTO " . T_PAYMENT_TRANSACTIONS . " (`userid`, `kind`, `amount`, `notes`) VALUES ({$wo['user']['user_id']}, 'PRO', {$amount1}, '{$notes}')");
                $create_payment = Wo_CreatePayment($pro_type);
                if ($mysqli)
                {
                    //record affiliate with fixed price
                    if ((!empty($_SESSION['ref']) || !empty($wo['user']['ref_user_id'])) && $wo['config']['affiliate_type'] == 0 && $wo['user']['referrer'] == 0)
                    {
                        if (!empty($_SESSION['ref']))
                        {
                            $ref_user_id = Wo_UserIdFromUsername($_SESSION['ref']);
                        }
                        elseif (!empty($wo['user']['ref_user_id']))
                        {
                            $ref_user_id = Wo_UserIdFromUsername($wo['user']['ref_user_id']);
                        }

                        if (!empty($ref_user_id) && is_numeric($ref_user_id))
                        {
                            $update_user = Wo_UpdateUserData($wo['user']['user_id'], array(
                                'referrer' => $ref_user_id,
                                'src' => 'Referrer'
                            ));
                            $update_balance = Wo_UpdateBalance($ref_user_id, $wo['config']['amount_ref']);
                            unset($_SESSION['ref']);
                        }
                    }
                    //record affiliate with percentage
                    if ((!empty($_SESSION['ref']) || !empty($wo['user']['ref_user_id'])) && $wo['config']['affiliate_type'] == 1 && $wo['user']['referrer'] == 0)
                    {
                        if ($wo['config']['amount_percent_ref'] > 0)
                        {
                            if (!empty($_SESSION['ref']))
                            {
                                $ref_user_id = Wo_UserIdFromUsername($_SESSION['ref']);
                            }
                            elseif (!empty($wo['user']['ref_user_id']))
                            {
                                $ref_user_id = Wo_UserIdFromUsername($wo['user']['ref_user_id']);
                            }
                            if (!empty($ref_user_id) && is_numeric($ref_user_id))
                            {
                                $update_user = Wo_UpdateUserData($wo['user']['user_id'], array(
                                    'referrer' => $ref_user_id,
                                    'src' => 'Referrer'
                                ));
                                $ref_amount = ($wo['config']['amount_percent_ref'] * $amount1) / 100;
                                $update_balance = Wo_UpdateBalance($ref_user_id, $ref_amount);
                                unset($_SESSION['ref']);
                            }
                        }
                        else if ($wo['config']['amount_ref'] > 0)
                        {
                            if (!empty($_SESSION['ref']))
                            {
                                $ref_user_id = Wo_UserIdFromUsername($_SESSION['ref']);
                            }
                            elseif (!empty($wo['user']['ref_user_id']))
                            {
                                $ref_user_id = Wo_UserIdFromUsername($wo['user']['ref_user_id']);
                            }
                            if (!empty($ref_user_id) && is_numeric($ref_user_id))
                            {
                                $update_user = Wo_UpdateUserData($wo['user']['user_id'], array(
                                    'referrer' => $ref_user_id,
                                    'src' => 'Referrer'
                                ));
                                $update_balance = Wo_UpdateBalance($ref_user_id, $wo['config']['amount_ref']);
                                unset($_SESSION['ref']);
                            }
                        }
                    }
                    $response_data = array(
                        'api_status' => 200,
                        'message' => 'upgraded'
                    );
                }
            }
            else
            {
                $error_code = 10;
                $error_message = 'something went wrong';
            }
        }
        else
        {
            $error_code = 9;
            $error_message = 'you are pro';
        }
    }

    if ($_POST['request'] == 'wallet')
    {
        try {
            if (empty($_POST['user_id']) || empty($_POST['order_id']) || empty($_POST['amount']) || !is_numeric($_POST['user_id'])) {
                throw new Exception('user_id , order_id , amount can not be empty');
            }

            $wo['user'] = Wo_UserData(Wo_Secure($_POST["user_id"]));
            if (!empty($wo['user'])) {

                $curl = curl_init();

                curl_setopt_array($curl, array(
                  CURLOPT_URL => 'https://sandbox.cashfree.com/pg/orders/' . $_POST['order_id'],
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => '',
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'GET',
                  CURLOPT_HTTPHEADER => array(
                    'accept: application/json',
                    'x-api-version: 2023-08-01',
                    'x-client-id: ' . $wo['config']['cashfree_client_key'],
                    'x-client-secret: ' . $wo['config']['cashfree_secret_key']
                  ),
                ));

                $response = curl_exec($curl);

                curl_close($curl);

                if (!empty($response)) {
                    $response = json_decode($response);

                    if (!empty($response->order_status) && $response->order_status == 'PAID') {
                        $wo["loggedin"] = true;
                        if (Wo_ReplenishingUserBalance($_POST['amount'])) {
                            $_POST['amount'] = Wo_Secure($_POST['amount']);
                            $create_payment_log = mysqli_query($sqlConnect, "INSERT INTO " . T_PAYMENT_TRANSACTIONS . " (`userid`, `kind`, `amount`, `notes`) VALUES ('" . $wo['user']['id'] . "', 'WALLET', '" . $_POST['amount'] . "', 'Cashfree')");

                            $user = Wo_UserData($wo['user']['user_id']);

                            $response_data = array(
                                'api_status' => 200,
                                'message' => 'payment successfully done',
                                'wallet' => $user['wallet'],
                                'balance' => $user['balance'],
                            );
                        } else {
                            throw new Exception('order status not paid');
                        }
                    }
                    else{
                        throw new Exception('order status not paid');
                    }
                }
                else{
                    throw new Exception('Something went wrong');
                }
            } else {
                throw new Exception('user not found');
            }
            
        } catch (Exception $e) {
            $error_code = 5;
            $error_message = $e->getMessage();
        }
    }

    if ($_POST['request'] == 'fund')
    {
        if (empty($_POST['txStatus']) || $_POST['txStatus'] != 'SUCCESS')
        {
            $response_data = array(
                'api_status' => '400',
                'errors' => array(
                    'error_id' => 6,
                    'error_text' => 'txStatus can not be empty or txStatus != SUCCESS'
                )
            );
            echo json_encode($response_data, JSON_PRETTY_PRINT);
            exit();
        }
        $orderId = $_POST["orderId"];
        $orderAmount = $_POST["orderAmount"];
        $referenceId = $_POST["referenceId"];
        $txStatus = $_POST["txStatus"];
        $paymentMode = $_POST["paymentMode"];
        $txMsg = $_POST["txMsg"];
        $txTime = $_POST["txTime"];
        $signature = $_POST["signature"];
        $data = $orderId . $orderAmount . $referenceId . $txStatus . $paymentMode . $txMsg . $txTime;
        $hash_hmac = hash_hmac('sha256', $data, $wo['config']['cashfree_secret_key'], true);
        $computedSignature = base64_encode($hash_hmac);
        if ($signature == $computedSignature)
        {
            $fund_id = Wo_Secure($_POST['fund_id']);
            $amount = Wo_Secure($_POST['amount']);
            $fund = $db->where('id', $fund_id)->getOne(T_FUNDING);

            if (!empty($fund) && !empty($fund_id) && !empty($amount))
            {

                $notes = "Doanted to " . mb_substr($fund->title, 0, 100, "UTF-8");

                $create_payment_log = mysqli_query($sqlConnect, "INSERT INTO " . T_PAYMENT_TRANSACTIONS . " (`userid`, `kind`, `amount`, `notes`) VALUES ({$wo['user']['user_id']}, 'DONATE', {$amount}, '{$notes}')");

                $admin_com = 0;
                if (!empty($wo['config']['donate_percentage']) && is_numeric($wo['config']['donate_percentage']) && $wo['config']['donate_percentage'] > 0)
                {
                    $admin_com = ($wo['config']['donate_percentage'] * $amount) / 100;
                    $amount = $amount - $admin_com;
                }
                $user_data = Wo_UserData($fund->user_id);
                $db->where('user_id', $fund->user_id)
                    ->update(T_USERS, array(
                    'balance' => $user_data['balance'] + $amount
                ));
                cache($fund->user_id, 'users', 'delete');
                $fund_raise_id = $db->insert(T_FUNDING_RAISE, array(
                    'user_id' => $wo['user']['user_id'],
                    'funding_id' => $fund_id,
                    'amount' => $amount,
                    'time' => time()
                ));
                $post_data = array(
                    'user_id' => Wo_Secure($wo['user']['user_id']) ,
                    'fund_raise_id' => $fund_raise_id,
                    'time' => time() ,
                    'multi_image_post' => 0
                );

                $id = Wo_RegisterPost($post_data);

                $notification_data_array = array(
                    'recipient_id' => $fund->user_id,
                    'type' => 'fund_donate',
                    'url' => 'index.php?link1=show_fund&id=' . $fund->hashed_id
                );
                Wo_RegisterNotification($notification_data_array);

                $response_data = array(
                    'api_status' => 200,
                    'message' => 'payment successfully'
                );
            }
            else
            {
                $error_code = 9;
                $error_message = 'amount fund_id empty';
            }
        }
        else
        {
            $error_code = 8;
            $error_message = 'something went wrong';
        }
    }
}

