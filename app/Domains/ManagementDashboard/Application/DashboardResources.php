<?php

namespace App\Domains\ManagementDashboard\Application;

class DashboardResources
{
    public const DEFINITIONS = [
        'products' => ['label' => 'المنتجات', 'description' => 'المنتجات الحالية وحالتها وخياراتها. العرض هنا للقراءة فقط.', 'table' => 'products'],
        'brands' => ['label' => 'العلامات التجارية', 'description' => 'العلامات الحالية والمنتجات المرتبطة بها، بما فيها السجلات المخفية.', 'table' => 'brands'],
        'concerns' => ['label' => 'الاهتمامات', 'description' => 'تصنيفات اهتمامات العناية والمنتجات المرتبطة بها.', 'table' => 'concerns'],
        'requests' => ['label' => 'الطلبات المبدئية', 'description' => 'طلبات مسجلة للتواصل والمراجعة الإدارية، وليست طلبات مؤكدة أو مبيعات.', 'table' => 'checkout_intents'],
    ];
}
