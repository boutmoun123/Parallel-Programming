<?php

namespace App\Modules\DailySalesReports\Requests;

use App\Http\Requests\ApiRequest;

class QueueDailySalesReportRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date'],
        ];
    }
}
