<?php

/**
 * TronAPI
 */

declare(strict_types=1);

namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Exception\TRC20Exception;
use IEXBase\TronAPI\Exception\TronException;

/**
 * Class TRC20Contract
 * @package TronAPI
 */
class TRC20Contract extends TBaseContract
{
    const TRX_TO_SUN = 1000000;

    /***
     * Maximum decimal supported by the Token
     *
     * @var integer|null
     */
    private ?int $_decimals = null;

    /***
     * Token Name
     *
     * @var string|null
     */
    private ?string $_name = null;

    /***
     * Token Symbol
     *
     * @var string|null
     */
    private ?string $_symbol = null;

    /**
     * Fee Limit
     *
     * @var integer
     */
    private int $feeLimit = 10;

    /**
     * Total Supply
     *
     * @var string|null
    */
    private ?string $_totalSupply = null;

    /**
     * Constructor
     *
     * @param Tron $tron
     * @param string $contractAddress
     */
    public function __construct(Tron $tron, string $contractAddress)
    {
        parent::__construct($tron, $contractAddress, null, __DIR__ . "/trc20.json");
    }

    /**
     * Debug Info
     *
     * @return array
     * @throws TronException
     */
    public function __debugInfo(): array
    {
        return $this->array();
    }

    /**
     * Clears cached values
     *
     * @return void
     */
    public function clearCached(): void
    {
        $this->_name = null;
        $this->_symbol = null;
        $this->_decimals = null;
        $this->_totalSupply = null;
    }

    /**
     *  All data
     *
     * @throws TronException
     */
    public function array(): array
    {
        return [
            'name' => $this->name(),
            'symbol' => $this->symbol(),
            'decimals' => $this->decimals(),
            'totalSupply' => $this->totalSupply(true)
        ];
    }

    /**
     * Get token name
     *
     * @return string
     * @throws TronException
     */
    public function name(): string
    {
        if ($this->_name) {
            return $this->_name;
        }

        $result = $this->trigger('name', null, []);
        $name = $result[0] ?? null;

        if (!is_string($name)) {
            throw new TRC20Exception('Failed to retrieve TRC20 token name');
        }

        $this->_name = $this->cleanStr($name);
        return $this->_name;
    }

    /**
     * Get symbol name
     *
     * @return string
     * @throws TronException
     */
    public function symbol(): string
    {
        if ($this->_symbol) {
            return $this->_symbol;
        }
        $result = $this->trigger('symbol', null, []);
        $code = $result[0] ?? null;

        if (!is_string($code)) {
            throw new TRC20Exception('Failed to retrieve TRRC20 token symbol');
        }

        $this->_symbol = $this->cleanStr($code);
        return $this->_symbol;
    }

    /**
     * The total number of tokens issued on the main network
     *
     * @param bool $scaled
     * @return string
     * @throws Exception\TronException
     * @throws TRC20Exception
     */
    public function totalSupply(): string
    {
        if (!$this->_totalSupply) {

            $result = $this->trigger('totalSupply', null, []);
            $totalSupply = $result[0]->toString() ?? null;

            if (!is_string($totalSupply) || !preg_match('/^[0-9]+$/', $totalSupply)) {
                throw new TRC20Exception('Failed to retrieve TRC20 token totalSupply');
            }

            $this->_totalSupply = $totalSupply;
        }

        return $this->_totalSupply;
    }

    /**
     * Maximum decimal supported by the Token
     *
     * @throws TRC20Exception
     * @throws TronException
     */
    public function decimals(): int
    {
        if ($this->_decimals) {
            return $this->_decimals;
        }

        $result = $this->trigger('decimals', null, []);
        $scale = intval($result[0]->toString() ?? null);

        if (is_null($scale)) {
            throw new TRC20Exception('Failed to retrieve TRC20 token decimals/scale value');
        }

        $this->_decimals = $scale;
        return $this->_decimals;
    }

    /**
     * Balance TRC20 contract
     *
     * @param string|null $address
     * @param bool $scaled
     * @return string
     * @throws TRC20Exception
     * @throws TronException
     */
    public function balanceOf(string $address = null): string
    {
        if(is_null($address))
            $address = $this->getTron()->address['base58'];

        $addr = str_pad($this->getTron()->address2HexString($address), 64, "0", STR_PAD_LEFT);
        $result = $this->trigger('balanceOf', $address, [$addr]);
        $balance = $result[0]->toString();

        if (!is_string($balance) || !preg_match('/^[0-9]+$/', $balance)) {
            throw new TRC20Exception(
                sprintf('Failed to retrieve TRC20 token balance of address "%s"', $addr)
            );
        }

        return $balance;
    }

    /**
     * Send TRC20 contract
     *
     * @param string $to
     * @param string $amount
     * @param string|null $from
     * @return array
     * @throws TRC20Exception
     * @throws TronException
     */
    public function transfer(string $to, string $amount, string $from = null): array
    {
        if($from == null) {
            $from = $this->getTron()->address['base58'];
        }

        $feeLimitInSun = bcmul((string)$this->feeLimit, (string)self::TRX_TO_SUN);

        if (!is_numeric($this->feeLimit) OR $this->feeLimit <= 0) {
            throw new TRC20Exception('fee_limit is required.');
        } else if($this->feeLimit > 1000) {
            throw new TRC20Exception('fee_limit must not be greater than 1000 TRX.');
        }

        $tokenAmount = bcmul($amount, bcpow("10", (string)$this->decimals(), 0), 0);

        $transfer = $this->getTron()->getTransactionBuilder()
            ->triggerSmartContract(
                $this->abiData,
                $this->getTron()->address2HexString($this->getAddress()),
                'transfer',
                [$this->getTron()->address2HexString($to), $tokenAmount],
                $feeLimitInSun,
                $this->getTron()->address2HexString($from)
            );

        $signedTransaction = $this->getTron()->signTransaction($transfer);
        $response = $this->getTron()->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     *  TRC20 All transactions
     *
     * @param string $address
     * @param int $limit
     * @return array
     *
     * @throws TronException
     */
    public function getTransactions(string $address, int $limit = 100): array
    {
        return $this->getTron()->getManager()
            ->request("v1/accounts/{$address}/transactions/trc20?limit={$limit}&contract_address={$this->getAddress()}", [], 'get');
    }

    /**
     * Get TRC20 token holder balances
     *
     * @throws TronException
     */
    public function getTRC20TokenHolderBalance(array $options = []): array
    {
        return $this->getTron()->getManager()
            ->request("v1/contracts/{$this->getAddress()}/tokens?".http_build_query($options), [],'get');
    }

    /**
     * @param string $str
     * @return string
     */
    public function cleanStr(string $str): string
    {
        return preg_replace('/[^\w.-]/', '', trim($str));
    }

    /**
     * Set fee limit
     *
     * @param int $fee_limit
     * @return TRC20Contract
     */
    public function setFeeLimit(int $fee_limit) : TRC20Contract
    {
        $this->feeLimit = $fee_limit;
        return $this;
    }
}
