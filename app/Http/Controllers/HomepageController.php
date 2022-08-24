<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

use App\Helpers\AmoHelper;
use App\Models\Leads;

class HomepageController extends Controller
{
    public function __invoke(): View
    {
        // идентификация в AmoCRM
        $amo = new AmoHelper(
            'e2fa4997-a848-4bcd-880a-6e11256ff266',
            'l6GQPV6MeqfHG4tVQ4PZIAtHulrhrFJR3wz3G1l7tQIicVHqYMhZTByRxTudf4b9',
            'https://c772-91-214-243-84.eu.ngrok.io'
        );

        // подключение к AmoCRM
        $amo->connect();

        // получение списка сделок
        $leads = $amo->getLeads();

        foreach ($leads as $lead) {
            // получение дополнительных полей
            $custom_fields_values = $lead->custom_fields_values;

            $fields = [];
            if (isset($custom_fields_values)) {
                ;
                foreach ($custom_fields_values as $custom_fields_value) {
                    $fields[$custom_fields_value->field_name] = $custom_fields_value->values[0]->value;
                }
            }

            // выгрузка собранных данных в БД
            Leads::updateOrCreate(
                ['name' => $lead->name],
                [
                    'price' => $lead->price,
                    'responsible_user_id' => $lead->responsible_user_id,
                    'custom_fields_values' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                    'account_id' => $lead->account_id,
                ]
            );
        }

        return view('homepage', ['leads' => $leads,]);
    }
}
