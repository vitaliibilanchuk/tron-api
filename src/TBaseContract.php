<?php

/**
 * TronAPI
 */

declare(strict_types=1);

namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Exception\TronException;

/**
 * Class TBaseContract
 */
class TBaseContract
{
    /**
     * ABI Data
     *
     * @var array|null
    */
    private $_abiData;

    /**
     * Base Tron object getter
     *
     * @return Tron
     */
    public function getAbi() : array {
        return $this->_abiData;
    }

    /**
     * Base Tron object
     *
     * @var Tron
     */
    private Tron $_tron;

    /**
     * Base Tron object getter
     *
     * @return Tron
     */
    public function getTron() : Tron {
        return $this->_tron;
    }
    
    /**
     * The smart contract which issued TRC20 Token
     *
     * @var string
    */
    private string $_contractAddress;

    /**
     * Return the smart contract address
     *
     * @return string
    */
    public function getAddress() : string {
        return $this->_contractAddress;
    }
    /**
     * Constructor
     *
     * @param Tron $tron
     * @param string $contractAddress
     * @param string|null $abi
     */
    public function __construct(Tron $tron, string $contractAddress, string $abi = null, string $abi_file = null)
    {
        $this->_tron = $tron;

        // If abi is absent, then it takes by default
        if(is_null($abi) && !is_null($abi_file)) {
            $abi = file_get_contents($abi_file);
        }

        if(!is_null($abi)) {
            $this->_abiData = json_decode($abi, true);
        }

        $this->_contractAddress = $contractAddress;
    }

    /**
     * Get token name
     *
     * @return string
     * @throws TronException
     */
    public function call(string $functionName, string $owner_address = null): string
    {
        $result = $this->trigger($functionName, $owner_address, []);
        $ret = $result[0] ?? null;

        if ($ret == null) {
            throw new TronException("Couldn't call [" . $functionName . "]");
        }

        return $ret;
    }

    /**
     * Get transaction info by contract address
     *
     * @throws TronException
     */
    public function getTransactionInfoByContract(array $options = []): array
    {
        return $this->getTron()->getManager()
            ->request("v1/contracts/{$this->getAddress()}/transactions?".http_build_query($options), [],'get');
    }

    /**
     * Config trigger
     *
     * @param $function
     * @param null $address
     * @param array $params
     * @return mixed
     * @throws TronException
     */
    protected function trigger($function, $address = null, array $params = [])
    {
        $owner_address = is_null($address) ? '410000000000000000000000000000000000000000' : $this->getTron()->address2HexString($address);

        return $this->getTron()->getTransactionBuilder()
            ->triggerConstantContract($this->getAbi(), $this->getTron()->address2HexString($this->getAddress()), $function, $params, $owner_address);
    }
}
