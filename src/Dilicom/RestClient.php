<?php

namespace Dilicom;

/**
 * Client to use the REST API
 *
 *  List of possible APIs:
 *  https://hub-dilicom.centprod.com/documentation/doku.php?id=hub_principal:examples_api_json
 */
class RestClient
{
    const ENV_TEST = "test";
    const ENV_PROD = "production";

    /**
     * Dilicom servers
     * @var array
     */
    protected static $servers = array(
        self::ENV_TEST => "https://hub-test.centprod.com",
        self::ENV_PROD => "https://hub-dilicom.centprod.com",
    );

    /**
     * User name (GLN in most case)
     * @var string
     */
    protected $user;

    /**
     * Password given by Dilicom
     * @var string
     */
    protected $password;

    /**
     * Reseller's country, ISO 3166-1
     * @var string
     */
    protected $resellerCountry;

    /**
     * Should the ssl certificate be checked?
     * @var boolean
     */
    protected $should_verify_ssl=true;

    /**
     * Environement. Possible values: test, production
     * @var string
     */
    protected $env;

    /**
     * Debug the client?
     * @var boolean
     */
    protected $enable_debug=false;

    /**
     * The connector used to send requests
     * @var ConnectorInterface
     */
    protected $connector;

    /**
     * Constructor
     *
     * @param string $user      User name (GLN in most case)
     * @param string $password  Password given by Dilicom
     */
    public function __construct($user, $password, $env)
    {
        $this->user = $user;
        $this->password = $password;

        if (!array_key_exists($env, self::$servers)) {
            throw new \InvalidArgumentException("no server for env $env");
        }
        $this->env = $env;

        // Defines a default connector to send requests
        // This default connector can be easily overloaded, @see ->setConnector()
        $this->connector = new HttpConnector(self::$servers[$this->env]);
    }

    /**
     * Get the ONIX notice for a given EAN13
     *
     * @param string $ean13
     * @param string $glnContractor
     * @param string $glnDistributor
     *
     * @return string
     */
    public function getOnixNotice($ean13, $glnContractor, $glnDistributor)
    {
        return $this->request("onix/getNotice", array(
            "query" => array(
                "glnContractor" => $glnContractor,
                "ean13" => $ean13,
                "glnDistributor" => $glnDistributor,
            ),
        ));
    }

    /**
     * Get the availability for a given EAN13
     *
     * @param  string $ean13
     * @param  string $glnDistributor
     * @param  int    $unitPrice
     * @param  int    $unitPriceExcludingTax
     *
     * @return string
     */
    public function getEbookAvailability($ean13, $glnDistributor, $unitPrice, $unitPriceExcludingTax)
    {
        return $this->getEbooksAvailabilities(array(
            array(
                "ean13" => $ean13,
                "glnDistributor" => $glnDistributor,
                "unitPrice" => $unitPrice,
                "unitPriceExcludingTax" => $unitPriceExcludingTax,
            )
        ));
    }

    /**
     * Checks if given ebook is well formed
     *
     * @param array $ebook
     * @throws InvalidArgumentException if given ebook does not contains necessary values
     */
    protected function checkEbookData(array $ebook)
    {
        if (!isset($ebook["ean13"], $ebook["glnDistributor"], $ebook["unitPrice"], $ebook["unitPriceExcludingTax"])) {
            throw new \InvalidArgumentException("Given ebook is badly formed. Expected something like array('ean13' => 'xxx', 'glnDistributor' => 'xxx', 'unitPrice' => 'x', 'unitPriceExcludingTax' => 'x'), got : " . serialize($ebook));
        }
    }

    /**
     * Asks to Dilicom if given ebooks are available
     *
     * @param array $ebooks
     */
    public function getEbooksAvailabilities($ebooks)
    {
        $query = array();

        if (!empty($this->resellerCountry)) {
            $query['country'] = $this->resellerCountry;
        }

        foreach ($ebooks as $i => $ebook) {
            $this->checkEbookData($ebook);
            $query["checkAvailabilityLines[$i].ean13"] = $ebook["ean13"];
            $query["checkAvailabilityLines[$i].glnDistributor"] = $ebook["glnDistributor"];
            $query["checkAvailabilityLines[$i].unitPrice"] = $ebook["unitPrice"];
            $query["checkAvailabilityLines[$i].unitPriceExcludingTax"] = $ebook["unitPriceExcludingTax"];
        }

        return $this->request("json/checkAvailability", array(
            "query" => $query,
        ));
    }

    /**
     * Disable the SSL certificate verification
     *
     * @return RestClient
     * @throws InvalidArgumentException if the ssl certificate verification is disable in production
     */
    public function disableSslVerification()
    {
        if ($this->env == self::ENV_PROD) {
            throw new \InvalidArgumentException("SSL certificate validation is mandatory in production!");
        }
        $this->should_verify_ssl = false;
        return $this;
    }

    /**
     * Activates debugging of the client
     *
     * @return RestClient
     */
    public function enableDebug()
    {
        $this->enable_debug = true;
        return $this;
    }

    /**
     * Overloads the default connector
     *
     * @param ConnectorInterface $connector
     * @return RestClient
     */
    public function setConnector(ConnectorInterface $connector)
    {
        $this->connector = $connector;
        return $this;
    }

    /**
     * @param string $resellerCountry
     *
     * @return $this
     */
    public function setResellerCountry($resellerCountry)
    {
        $this->resellerCountry = $resellerCountry;
        return $this;
    }

    /**
     * Requests Dilicom
     *
     * @param  string $api      API Dilicom
     * @param  array  $options  Options to pass to the connector
     * @return mixed
     */
    protected function request($api, $options=array())
    {
        return $this->connector->get("/v3/hub-numerique-api/$api", null, array_merge(array(
            "auth"      => array($this->user, $this->password),
            "verify"    => $this->should_verify_ssl,
            "debug"     => $this->enable_debug
        ), $options));
    }

}
