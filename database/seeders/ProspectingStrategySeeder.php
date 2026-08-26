<?php

namespace Database\Seeders;

use App\Models\ProspectingIndustryProfile;
use App\Models\ProspectingLocation;
use Illuminate\Database\Seeder;

class ProspectingStrategySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->industries() as $industry) {
            ProspectingIndustryProfile::query()->updateOrCreate(['slug' => $industry['slug']], $industry);
        }

        foreach ([
            ['name' => 'Doncaster', 'slug' => 'doncaster', 'priority' => 100],
            ['name' => 'Sheffield', 'slug' => 'sheffield', 'priority' => 95],
            ['name' => 'Rotherham', 'slug' => 'rotherham', 'priority' => 90],
            ['name' => 'Barnsley', 'slug' => 'barnsley', 'priority' => 85],
            ['name' => 'Wakefield', 'slug' => 'wakefield', 'priority' => 80],
            ['name' => 'Leeds', 'slug' => 'leeds', 'priority' => 75],
            ['name' => 'York', 'slug' => 'york', 'priority' => 70],
        ] as $location) {
            ProspectingLocation::query()->updateOrCreate(['slug' => $location['slug']], [...$location, 'enabled' => true]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function industries(): array
    {
        return [
            $this->industry('Kitchen companies / kitchen fitters', 'kitchen-companies', 100, 15000, 'very_high', ['fitted kitchens', 'kitchen fitters', 'kitchen installation', 'kitchen design', 'bespoke kitchens', 'kitchen showroom', 'luxury kitchens'], ['fitted kitchens', 'kitchen fitters', 'bespoke kitchens', 'luxury kitchens'], 'Very high-value design and installation projects make a single additional customer commercially meaningful.'),
            $this->industry('Bathroom companies / bathroom fitters', 'bathroom-companies', 95, 9000, 'high', ['bathroom fitters', 'bathroom installation', 'bathroom design', 'bathroom showroom', 'luxury bathrooms', 'bathroom renovation'], ['bathroom fitters', 'bathroom installation', 'luxury bathrooms', 'bathroom renovation'], 'Substantial renovation projects with strong local transactional search intent.'),
            $this->industry('Builders / home extensions', 'builders-home-extensions', 98, 30000, 'very_high', ['builders', 'house extensions', 'extension builders', 'home extensions', 'renovation company', 'property renovation', 'loft conversions'], ['house extensions', 'extension builders', 'home extensions', 'property renovation'], 'Prioritises larger residential projects rather than low-value handyman searches.'),
            $this->industry('Landscaping companies', 'landscaping-companies', 90, 8000, 'high', ['landscapers', 'landscape gardeners', 'garden design', 'garden landscaping', 'patios', 'driveways', 'outdoor living', 'garden rooms'], ['garden design', 'garden landscaping', 'driveways', 'garden rooms'], 'Focuses on substantial landscaping and outdoor projects rather than maintenance gardening.'),
            $this->industry('Solar installers', 'solar-installers', 96, 11000, 'very_high', ['solar panel installers', 'solar installation', 'solar panels', 'solar battery installation', 'home battery installation', 'solar and battery installers'], ['solar panel installers', 'solar installation', 'solar battery installation', 'solar and battery installers'], 'High-value installations where local search visibility can produce valuable enquiries.'),
            $this->industry('EV charger installers', 'ev-charger-installers', 88, 2500, 'high', ['EV charger installation', 'home EV charger installer', 'EV charger installers', 'electric car charger installation', 'commercial EV charging'], ['EV charger installation', 'home EV charger installer', 'commercial EV charging'], 'Commercial and multi-install opportunities improve the value of otherwise smaller domestic jobs.'),
            $this->industry('Private dentists', 'private-dentists', 94, 5000, 'very_high', ['private dentist', 'cosmetic dentist', 'dental implants', 'Invisalign', 'cosmetic dentistry', 'teeth whitening'], ['dental implants', 'Invisalign', 'cosmetic dentist', 'private dentist'], 'High-value private treatments make commercially focused local rankings especially attractive.'),
            $this->industry('Aesthetics clinics', 'aesthetics-clinics', 86, 1500, 'high', ['aesthetics clinic', 'skin clinic', 'cosmetic clinic', 'laser clinic', 'facial aesthetics', 'aesthetic treatments'], ['aesthetics clinic', 'skin clinic', 'laser clinic', 'facial aesthetics'], 'Repeat treatments and strong local discovery behaviour can support ongoing acquisition spend.'),
            $this->industry('Accountants', 'accountants', 65, 3000, 'high', ['accountant', 'accountants', 'small business accountant', 'limited company accountant', 'tax accountant'], ['small business accountant', 'limited company accountant', 'tax accountant'], 'Recurring client value and commercially specific searches support a lower Tier 2 priority.'),
            $this->industry('Mortgage brokers', 'mortgage-brokers', 68, 2500, 'high', ['mortgage broker', 'mortgage adviser', 'first time buyer mortgage adviser', 'remortgage broker'], ['mortgage broker', 'first time buyer mortgage adviser', 'remortgage broker'], 'High-intent searches can lead directly to valuable completed cases.'),
            $this->industry('Estate agents', 'estate-agents', 58, 3000, 'high', ['estate agents', 'letting agents', 'property agents', 'estate agent'], ['estate agents', 'letting agents', 'estate agent'], 'Local visibility matters, though larger sites and competitive markets reduce default priority.'),
            $this->industry('Solicitors', 'solicitors', 70, 5000, 'high', ['solicitors', 'conveyancing solicitor', 'family solicitor', 'divorce solicitor', 'employment solicitor', 'commercial solicitor'], ['conveyancing solicitor', 'family solicitor', 'employment solicitor', 'commercial solicitor'], 'Commercially useful legal matters can justify sustained local search investment.'),
            $this->industry('Roofing companies', 'roofing-companies', 50, 6000, 'high', ['roofer', 'roofing company', 'roof repairs', 'new roof', 'flat roofing', 'commercial roofing'], ['roofing company', 'new roof', 'flat roofing', 'commercial roofing'], 'Roofers remain valuable prospects but deliberately sit below the core high-priority categories.'),
            $this->industry('Wedding venues', 'wedding-venues', 72, 10000, 'very_high', ['wedding venues', 'wedding venue', 'barn wedding venue', 'luxury wedding venue', 'wedding reception venue'], ['wedding venues', 'barn wedding venue', 'luxury wedding venue'], 'A single booking can have very high value and customers commonly discover venues through search.'),
        ];
    }

    /** @param array<int, string> $serviceKeywords @param array<int, string> $searchKeywords @return array<string, mixed> */
    private function industry(string $name, string $slug, int $priority, int $value, string $band, array $serviceKeywords, array $searchKeywords, string $notes): array
    {
        return ['name' => $name, 'slug' => $slug, 'enabled' => true, 'priority' => $priority, 'estimated_customer_value' => $value, 'customer_value_band' => $band, 'service_keywords' => $serviceKeywords, 'search_keywords' => $searchKeywords, 'minimum_position' => 8, 'maximum_position' => 50, 'maximum_site_size' => 30, 'automatic_import_score' => 65, 'notes' => $notes];
    }
}
