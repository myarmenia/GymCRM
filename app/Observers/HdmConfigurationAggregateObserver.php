<?php

namespace App\Observers;

use App\Models\HdmCashier;
use App\Models\HdmConfig;

class HdmConfigurationAggregateObserver
{
    public function created(HdmCashier $cashier): void
    {
        $this->incrementConfigurationVersion($cashier);
    }

    public function updated(HdmCashier $cashier): void
    {
        $this->incrementConfigurationVersion($cashier);
    }

    public function trashed(HdmCashier $cashier): void
    {
        $this->incrementConfigurationVersion($cashier);
    }

    public function restored(HdmCashier $cashier): void
    {
        $this->incrementConfigurationVersion($cashier);
    }

    private function incrementConfigurationVersion(HdmCashier $cashier): void
    {
        HdmConfig::query()
            ->withTrashed()
            ->whereKey($cashier->hdm_config_id)
            ->increment('version');
    }
}
