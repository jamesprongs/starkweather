<?php

/**
 *
 * This controller helps to obtain data from wweather.gov for the sake of simplifying the data output for our home server.
 *
 * TODO: 1. Save this data into a database on a cron job. 2. Pull the data exclusively from the local database.
 * TODO: Move most of this to a location class which can get lat and long from the browser or other detection tool.
 */

namespace Drupal\starkweather\Controller;

use \Drupal\Core\Controller\ControllerBase;
use \Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Http\ClientFactory;
use Drupal\http_client_manager\HttpClientManagerFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use Drupal\starkweather\Model\StarkweatherForecast;
use Drupal\starkweather\Model\StarkweatherHourlyForecast;


class StarkweatherController extends ControllerBase {

  protected $client_factory;
  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * @param $lat
   */
  protected $lat;

  /**
   * @param $long;
   */
  protected $long;

  protected $location;
  protected $city;
  protected $state;
  protected $county;
  protected $cwa;
  protected $gridId;
  protected $gridX;
  protected $gridY;

  private function __construct(ClientFactory $http_client_factory) {
    $this->client_factory = $http_client_factory;
    $this->client = $http_client_factory->fromOptions([
      'base_uri' => 'https://api.weather.gov/openapi.json',
      'verify' => false,
      'header' => [
          'Accept' => 'application/json',
      ],
      'http_errors' => false,
    ]);
    // TODO: default lat and long is on top of my house, until I can implement some function to locate user position.
    $this->lat = '38.894138';
    $this->long = '-77.2072135';
    $this->loadWeatherPointData();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(

      $container->get('http_client_factory')
    );
  }

  // not used
  public function loadNOAAData() {
    /** @var \GuzzleHttp\Client $client */
    $client = \Drupal::service('http_client_factory')->fromOptions([
      //https://api.weather.gov/openapi.json
      'base_uri' => 'https://api.weather.gov/',
    ]);
  }

  public function setLatitude(string $lat) {
    $this->lat = $lat;
  }

  public function getLatitude() {
    return $this->lat;
  }

  public function setLongitude(string $long) {
    $this->long = $long;
  }

  public function getLongitude() {
    return $this->long;
  }

  private function setRelativeLocation($data) {
    $this->location = array_key_exists('relativeLocation', $data) ? $data['relativeLocation'] : $data['detail'];
  }

  public function getRelativeLocation() {
    return $this->location;
  }

  /* I forget if there is a reason that I am not using $data parameter */
  private function setCity($data) {
    $this->city = array_key_exists('city', $this->location['properties']) ? $this->location['properties']['city'] : 'City Unknown';
  }

  public function getCity() {
    return $this->city;
  }

  private function setState($data) {
    $this->state = array_key_exists('state', $this->location['properties']) ? $this->location['properties']['state'] : 'Unknown State';
  }

  public function getState() {
    return $this->state;
  }

  private function setCountyName($data) {
    if(array_key_exists('county', $data)) {
      $response = $this->client->get($data);
      $countyData = Json::decode($response->getBody());
      $this->county = $countyData['properties']['name'];
    } else {
      $this->county = "Unknown County";
    }
  }

  public function getCountyName() {
    return $this->county;
  }

  public function getCwa() {
    return $this->cwa;
  }

  private function setCwa($data) {
    $this->gridId = array_key_exists('cwa', $this->location['properties']) ? $this->location['properties']['cwa'] : 'Unknown';
  }

  public function getGridId() {
    return $this->gridId;
  }

  private function setGridId($data) {
    $this->gridId = array_key_exists('gridId', $this->location['properties']) ? $this->location['properties']['gridId'] : 'Unknown';
  }

  public function getGridX() {
    return $this->gridX;
  }

  private function setX($data) {
    $this->gridX = array_key_exists('gridX', $this->location['properties']) ? $this->location['properties']['gridX'] : 'Unknown';
  }

  public function getGridY() {
    return $this->gridY;
  }

  private function setGridY($data) {
    $this->gridY = array_key_exists('gridY', $this->location['properties']) ? $this->location['properties']['gridY'] : 'Unknown';
  }

  public function getForecastOffice() {
    //$data = $this->loadWeatherPointData();
    $response = $this->client->get('offices/'. $this->cwa);
    $forecastOffice = Json::decode($response->getBody());
    return $forecastOffice;
  }

  public function getDailyForecastLink() {
    return '/gridpoints/'. $this->cwa . '/'. $this->gridX . ',' . $this->gridY . '/forecast';
  }

  /*
  public function getForecast() {
    //$data = $this->loadWeatherPointData();
    $response = $this->client->get('gridpoints/'. $this->cwa . '/'. $this->gridX . ',' . $this->gridY . '/forecast');
    $forecastData = Json::decode($response->getBody());
    return $forecastData;
  }
  */

  public function getHourlyForecastLink() {
    //$data = $this->loadWeatherPointData();
    return '/gridpoints/'. $this->cwa . '/'. $this->gridX . ',' . $this->gridY . '/forecast/hourly';
  }

  public function getForecastZoneData() {
    //$data = $this->loadWeatherPointData();
    $response = $this->client->get($this->location['properties']['forecastZone']);
    $forecastZone = Json::decode($response->getBody());
    return $forecastZone;
  }

  public function getForecastGridData() {
    //$data = $this->loadWeatherPointData();
    //$properties = $data['properties'];
    $response = $this->client->get('gridpoints/'. $this->cwa . '/'. $this->gridX . ',' . $this->gridY );
    $forecastData = Json::decode($response->getBody());
    return $forecastData;
  }

  public function getRadarStation() {
    $data = $this->loadWeatherPointData();
    return $data['properties']['radarStation'];
  }
  // almost everything from here up should be in its own class ... Maybe something like "StarkweatherLocation

  /**
   *
   * properties[] =>
  "cwa" => "LWX"
  "forecastOffice" => "https://api.weather.gov/offices/LWX"
  "gridId" => "LWX"
  "gridX" => 91
  "gridY" => 70
  "forecast" => "https://api.weather.gov/gridpoints/LWX/91,70/forecast"
  "forecastHourly" => "https://api.weather.gov/gridpoints/LWX/91,70/forecast/hourly"
  "forecastGridData" => "https://api.weather.gov/gridpoints/LWX/91,70"
  "observationStations" => "https://api.weather.gov/gridpoints/LWX/91,70/stations"
  "relativeLocation" => array:3 [▶]
  "forecastZone" => "https://api.weather.gov/zones/forecast/VAZ053"
  "county" => "https://api.weather.gov/zones/county/VAC059"
  "fireWeatherZone" => "https://api.weather.gov/zones/fire/VAZ053"
  "timeZone" => "America/New_York"
  "radarStation" => "KLWX"
   */

    /**
     * Loads weather point from NOAA api.weather.gov
     * Defaults lat and long to my home.
     * 7604 Rudyard St. Falls Church, VA.
     * 38.894138,
     *
     * @param $lat
     * @param $long
     *
     * @return mixed
     */
    public function loadWeatherPointData() {
      $response = $this->client->get('points/'.$this->lat.','.$this->long);
      //dump($response->getStatusCode());
     // $response = $this->client->request('GET', 'points/'.$this->lat.','.$this->long);
  //    if($response->getStatusCode() != 200) {

    //  }
      $data = Json::decode($response->getBody());
      if(array_key_exists('properties', $data)) {
        $data = $data['properties'];
        // we are not grabbing all of relativeLocation. We just need the properties portion.
        $this->location = array_key_exists('relativeLocation', $data) ? $data['relativeLocation']['properties'] : $data['detail'];
        $this->city = array_key_exists('city', $this->location) ? $this->location['city'] : 'City Unknown';
        $this->state = array_key_exists('state', $this->location) ? $this->location['state'] : 'Unknown State';
        $this->cwa = array_key_exists('cwa', $data) ? $data['cwa'] : 'Unknown';
        $this->gridId = array_key_exists('gridId', $data) ? $data['gridId'] : 'Unknown';
        $this->gridX = array_key_exists('gridX', $data) ? $data['gridX'] : 'Unknown';
        $this->gridY = array_key_exists('gridY', $data) ? $data['gridY'] : 'Unknown';
        $countyData = array_key_exists('county', $data) ? $data['county'] : null;
        if($countyData) {
          $response = $this->client->get($countyData);
          $countyData = Json::decode($response->getBody());
          $this->county = $countyData['properties']['name'];
        } else  {
          $this->county = 'Unknown County';
        }
      } else {
        $this->location = 'City Unknown';
          $this->city = 'City Unknown';
          $this->state = 'State Unknown';
          $this->county = 'Unknown County';
      }

    }

   /**
    * @return array
    */
  public function exportStarkweatherData() {
    $forecast = new StarkweatherForecast($this->client_factory, $this->getDailyForecastLink());
    $hourlyForecast = new StarkweatherHourlyForecast($this->client_factory, $this->getHourlyForecastLink());
    //dump($forecast->getForecast());
    $json_array = array(
      'data' => [
        'city' => $this->getCity(),
        'state' => $this->getState(),
      'county' => $this->getCountyName(),
        'dailyForecastData' => $forecast->getDailyForecastData(),
        'hourlyForecastData' => $hourlyForecast->getForecastData()
      ]
    );
    return new JsonResponse($json_array);
  }


  /**
   * @return array
   *
   * Display the help page.
   */
  public function helpPage() {
    return [
      '#markup' => $this->t('Hello from the Starkweather module! <br/><br />'
        . $this->getCity()  . '<br />' . $this->getState() . '<br />' . $this->getCountyName() . '<br />CWA: ' . $this->getCwa() . '<br />Grid ID: '
        . $this->getGridId() . '<br />' . $this->getGridY() . '<br />' . $this->getGridX() . '<br />' . $this->getDailyForecastLink() . '<br />' . $this->getHourlyForecastLink() . '<br />' . '<a href="http://192.168.1.69/web/starkweather/json">http://192.168.1.69/web/starkweather/json</a>'
       // . '<br /><br />' . dump($this->getForecastData())
      ),
    ];
  }

}
