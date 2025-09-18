<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'name' => 'United States',
                'iso_code' => 'US',
                'states' => [
                    ["name" => "Alabama"],
                    ["name" => "Alaska"],
                    ["name" => "Arizona"],
                    ["name" => "Arkansas"],
                    ["name" => "California"],
                    ["name" => "Colorado"],
                    ["name" => "Connecticut"],
                    ["name" => "Delaware"],
                    ["name" => "Florida"],
                    ["name" => "Georgia"],
                    ["name" => "Hawaii"],
                    ["name" => "Idaho"],
                    ["name" => "Illinois"],
                    ["name" => "Indiana"],
                    ["name" => "Iowa"],
                    ["name" => "Kansas"],
                    ["name" => "Kentucky"],
                    ["name" => "Louisiana"],
                    ["name" => "Maine"],
                    ["name" => "Maryland"],
                    ["name" => "Massachusetts"],
                    ["name" => "Michigan"],
                    ["name" => "Minnesota"],
                    ["name" => "Mississippi"],
                    ["name" => "Missouri"],
                    ["name" => "Montana"],
                    ["name" => "Nebraska"],
                    ["name" => "Nevada"],
                    ["name" => "New Hampshire"],
                    ["name" => "New Jersey"],
                    ["name" => "New Mexico"],
                    ["name" => "New York"],
                    ["name" => "North Carolina"],
                    ["name" => "North Dakota"],
                    ["name" => "Ohio"],
                    ["name" => "Oklahoma"],
                    ["name" => "Oregon"],
                    ["name" => "Pennsylvania"],
                    ["name" => "Rhode Island"],
                    ["name" => "South Carolina"],
                    ["name" => "South Dakota"],
                    ["name" => "Tennessee"],
                    ["name" => "Texas"],
                    ["name" => "Utah"],
                    ["name" => "Vermont"],
                    ["name" => "Virginia"],
                    ["name" => "Washington"],
                    ["name" => "West Virginia"],
                    ["name" => "Wisconsin"],
                    ["name" => "Wyoming"]
                ],
            ],
            [
                'name' => 'Canada',
                'iso_code' => 'CA',
                'states' => [
                    ["name" => "Alberta"],
                    ["name" => "British Columbia"],
                    ["name" => "Manitoba"],
                    ["name" => "New Brunswick"],
                    ["name" => "Newfoundland and Labrador"],
                    ["name" => "Nova Scotia"],
                    ["name" => "Ontario"],
                    ["name" => "Prince Edward Island"],
                    ["name" => "Quebec"],
                    ["name" => "Saskatchewan"],
                    ["name" => "Northwest Territories"],
                    ["name" => "Nunavut"],
                    ["name" => "Yukon"]
                ],
            ],
            [
                'name' => 'United Kingdom',
                'iso_code' => 'UK',
                'states' => [
                    ["name" => "Bedfordshire"],
                    ["name" => "Berkshire"],
                    ["name" => "Bristol"],
                    ["name" => "Buckinghamshire"],
                    ["name" => "Cambridgeshire"],
                    ["name" => "Cheshire"],
                    ["name" => "City of London"],
                    ["name" => "Cornwall"],
                    ["name" => "Cumbria"],
                    ["name" => "Derbyshire"],
                    ["name" => "Devon"],
                    ["name" => "Dorset"],
                    ["name" => "Durham"],
                    ["name" => "East Riding of Yorkshire"],
                    ["name" => "East Sussex"],
                    ["name" => "Essex"],
                    ["name" => "Gloucestershire"],
                    ["name" => "Greater London"],
                    ["name" => "Greater Manchester"],
                    ["name" => "Hampshire"],
                    ["name" => "Herefordshire"],
                    ["name" => "Hertfordshire"],
                    ["name" => "Isle of Wight"],
                    ["name" => "Kent"],
                    ["name" => "Lancashire"],
                    ["name" => "Leicestershire"],
                    ["name" => "Lincolnshire"],
                    ["name" => "Merseyside"],
                    ["name" => "Norfolk"],
                    ["name" => "North Yorkshire"],
                    ["name" => "Northamptonshire"],
                    ["name" => "Northumberland"],
                    ["name" => "Nottinghamshire"],
                    ["name" => "Oxfordshire"],
                    ["name" => "Rutland"],
                    ["name" => "Shropshire"],
                    ["name" => "Somerset"],
                    ["name" => "South Yorkshire"],
                    ["name" => "Staffordshire"],
                    ["name" => "Suffolk"],
                    ["name" => "Surrey"],
                    ["name" => "Tyne and Wear"],
                    ["name" => "Warwickshire"],
                    ["name" => "West Midlands"],
                    ["name" => "West Sussex"],
                    ["name" => "West Yorkshire"],
                    ["name" => "Wiltshire"],
                    ["name" => "Worcestershire"]
                ]
            ],
            [
                'name' => 'Australia',
                'iso_code' => 'AU',
                'states' => [
                    ["name" => "New South Wales"],
                    ["name" => "Victoria"],
                    ["name" => "Queensland"],
                    ["name" => "Western Australia"],
                    ["name" => "South Australia"],
                    ["name" => "Tasmania"],
                    ["name" => "Northern Territory"],
                    ["name" => "Australian Capital Territory"]
                ],
            ],
            [
                'name' => 'Germany',
                'iso_code' => 'DE',
                'states' => [
                    ["name" => "Baden-Württemberg"],
                    ["name" => "Bavaria"],
                    ["name" => "Berlin"],
                    ["name" => "Brandenburg"],
                    ["name" => "Bremen"],
                    ["name" => "Hamburg"],
                    ["name" => "Hesse"],
                    ["name" => "Lower Saxony"],
                    ["name" => "Mecklenburg-Vorpommern"],
                    ["name" => "North Rhine-Westphalia"],
                    ["name" => "Rhineland-Palatinate"],
                    ["name" => "Saarland"],
                    ["name" => "Saxony"],
                    ["name" => "Saxony-Anhalt"],
                    ["name" => "Schleswig-Holstein"],
                    ["name" => "Thuringia"]
                ]
            ],
            [
                'name' => 'France',
                'iso_code' => 'FR',
                'states' => [
                    // Metropolitan France
                    ["name" => "Auvergne-Rhône-Alpes"],
                    ["name" => "Bourgogne-Franche-Comté"],
                    ["name" => "Brittany"],
                    ["name" => "Centre-Val de Loire"],
                    ["name" => "Corsica"],
                    ["name" => "Grand Est"],
                    ["name" => "Hauts-de-France"],
                    ["name" => "Île-de-France"],
                    ["name" => "Normandy"],
                    ["name" => "Nouvelle-Aquitaine"],
                    ["name" => "Occitanie"],
                    ["name" => "Pays de la Loire"],
                    ["name" => "Provence-Alpes-Côte d'Azur"],

                    // Overseas Regions
                    ["name" => "Guadeloupe"],
                    ["name" => "Martinique"],
                    ["name" => "French Guiana"],
                    ["name" => "Réunion"],
                    ["name" => "Mayotte"]
                ]
            ],
        ];

        $currency = \App\Models\Currency::where('name', 'USD')->first();
        foreach ($countries as $payload) {
            $country = Country::make();
            $country->currency_id = $currency->id;
            $country->toFill($payload);

            $country->save();
            foreach ($payload['states'] as $state) {
                $state['country_id'] = $country->id;
                \App\Models\State::create($state);
            }
        }
    }
}
