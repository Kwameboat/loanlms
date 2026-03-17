<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BorrowerFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;
        $gender = fake()->randomElement(['male', 'female']);
        $firstName = $gender === 'male'
            ? fake()->randomElement(['Kwabena','Kofi','Kwame','Yaw','Fiifi','Nana','Kojo','Ebo'])
            : fake()->randomElement(['Ama','Abena','Akosua','Adwoa','Efua','Adjoa','Afia','Araba']);
        $lastName = fake()->randomElement(['Asante','Mensah','Boateng','Owusu','Darko','Appiah','Frimpong','Sarpong','Bediako','Acheampong']);

        return [
            'borrower_number'    => sprintf('BRW-%04d', $counter++),
            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'other_names'        => null,
            'gender'             => $gender,
            'date_of_birth'      => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'ghana_card_number'  => 'GHA-' . fake()->numerify('#########') . '-' . fake()->numerify('#'),
            'phone_primary'      => '0' . fake()->randomElement(['20','24','26','27','28','50','55','59']) . fake()->numerify('#######'),
            'email'              => strtolower($firstName . '.' . $lastName . '@example.com'),
            'address'            => fake()->streetAddress() . ', ' . fake()->randomElement(['Accra','Kumasi','Takoradi','Tamale','Cape Coast']),
            'region'             => fake()->randomElement(['Greater Accra','Ashanti','Western','Northern','Eastern','Central']),
            'employment_status'  => fake()->randomElement(['employed','self_employed','business_owner','retired']),
            'employer_name'      => fake()->company(),
            'monthly_income'     => fake()->randomElement([1200,1500,1800,2000,2500,3000,3500,4000,5000,6000]),
            'mobile_money_number'=> '0' . fake()->randomElement(['24','26','27']) . fake()->numerify('#######'),
            'mobile_money_provider' => fake()->randomElement(['mtn','vodafone','airteltigo']),
            'branch_id'          => 1,
            'credit_score'       => fake()->numberBetween(30, 95),
            'status'             => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function blacklisted(): static
    {
        return $this->state(['status' => 'blacklisted', 'credit_score' => fake()->numberBetween(10, 35)]);
    }
}
