<?php

namespace Drupal\starkweather\Model;

use \Drupal\Component\Serialization\Json;
use \Drupal\Component\HttpFoundation\JsonResponse;
use \Drupal\Core\Http\ClientFactory;
use \Drupal\http_client_manger\HttpClientManagerFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;

class StarkweatherHourlyForecast {

  protected $link;
  protected $client;

  protected $forecast;

  public function __construct(ClientFactory $http_client_factory, $link = null) {
    $this->link = $link;
    if('https://api.weather.gov') {
      $this->client = $http_client_factory->fromOptions([
        'base_uri' => 'https://api.weather.gov',
        'verify' => false,
        'header' => [
            'Accept' => 'application/json',
        ],
        'http_errors' => false,
      ]);
    } else {
      $this->client = null;
    }
  }

  public function getForecastData() {
    $response = $this->client->get($this->link);
    $data = Json::decode($response->getBody());
    $forecast = array_key_exists('properties', $data) ? $data['properties'] : null;
    return $forecast;
  }

  public function getHourlyForecastData() {
    $forecastData = $this->getForecastData();
    dump($forecastData);
    return array_key_exists('periods', $forecastData) ? $forecastData['periods'] : 'Unknown';
  }

}
