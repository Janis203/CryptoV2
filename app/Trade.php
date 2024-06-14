<?php

namespace App;

use Exception;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class Trade
{
    private ApiClient $apiClient;
    private string $transactions;
    private float $balance = 1000.0;

    public function __construct(ApiClient $apiClient, string $transactions = 'transactions.json')
    {
        $this->apiClient = $apiClient;
        $this->transactions = $transactions;
        if (!file_exists($this->transactions)) {
            file_put_contents($this->transactions, json_encode(['balance' => $this->balance, 'transactions' => []]));
        }
    }

    public function list(): void
    {
        try {
            $data = $this->apiClient->getList(1, 10, 'USD');
            if (isset($data["data"])) {
                $output = new ConsoleOutput();
                $table = new Table($output);
                $table->setHeaders(["Rank", "Name", "Symbol", "Price"]);
                foreach ($data["data"] as $crypto) {
                    $currency = new Currency(
                        $crypto["name"],
                        $crypto["symbol"],
                        $crypto["cmc_rank"],
                        $crypto["quote"]["USD"]["price"]
                    );
                    $table->addRow([
                        $currency->getRank(),
                        $currency->getName(),
                        $currency->getSymbol(),
                        $currency->getPrice()
                    ]);
                }
                $table->render();
            } else {
                exit ("error getting data");
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    public function search(string $symbol): void
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, 'USD');
            if (isset($data["data"])) {
                $crypto = $data["data"][$symbol];
                $currency = new Currency(
                    $crypto["name"],
                    $crypto["symbol"],
                    $crypto["cmc_rank"],
                    $crypto["quote"]["USD"]["price"]
                );
                $output = new ConsoleOutput();
                $table = new Table($output);
                $table->setHeaders(["Rank", "Name", "Symbol", "Price"]);
                $table->addRow([
                    $currency->getRank(),
                    $currency->getName(),
                    $currency->getSymbol(),
                    $currency->getPrice()
                ]);
                $table->render();
            } else {
                exit ("error getting data");
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    private function getTransactions(): array
    {
        return json_decode(file_get_contents($this->transactions), true);
    }

    private function saveTransactions(array $data): void
    {
        file_put_contents($this->transactions, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function purchase(string $symbol): void
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, "USD");
            if (isset($data['data'][$symbol])) {
                $amount = (float)readline("Enter amount of $symbol to buy ");
                if ($amount <= 0) {
                    echo "Enter positive amount " . PHP_EOL;
                    return;
                }
                $price = $data["data"][$symbol]["quote"]["USD"]["price"];
                $cost = $price * $amount;
                $transactions = $this->getTransactions();
                if ($transactions['balance'] < $cost) {
                    echo "Insufficient funds to buy $amount $symbol " . PHP_EOL;
                    return;
                }
                $transactions['balance'] -= $cost;
                $transactions['transactions'][] = [
                    'type' => 'purchase',
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'cost' => $cost,
                    'time' => date('Y-m-d H:i:s')
                ];
                $this->saveTransactions($transactions);
                echo "Purchased $amount $symbol for \$$cost" . PHP_EOL;
            } else {
                echo $symbol . " not found" . PHP_EOL;
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    public function sell(string $symbol): void
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, "USD");
            if (isset($data['data'][$symbol])) {
                $amount = (float)readline("Enter amount of $symbol to sell ");
                if ($amount <= 0) {
                    echo "Enter positive amount " . PHP_EOL;
                    return;
                }
                $price = $data["data"][$symbol]["quote"]["USD"]["price"];
                $value = $price * $amount;
                $bought = 0;
                $sold = 0;
                $transactions = $this->getTransactions();
                foreach ($transactions['transactions'] as $transaction) {
                    if ($transaction['type'] === "purchase" && $transaction['symbol'] === $symbol) {
                        $bought += $transaction['amount'];
                    } elseif ($transaction['type'] === "sell" && $transaction['symbol'] === $symbol) {
                        $sold += $transaction['amount'];
                    }
                }
                $availableAmount = $bought - $sold;
                if ($amount > $availableAmount) {
                    echo "Insufficient amount of $symbol to sell " . PHP_EOL;
                    return;
                }
                $transactions['balance'] += $value;
                $transactions['transactions'][] = [
                    'type' => 'sell',
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'value' => $value,
                    'time' => date('Y-m-d H:i:s')
                ];
                $this->saveTransactions($transactions);
                echo "Sold $amount $symbol for \$$value" . PHP_EOL;
            } else {
                echo $symbol . " not found" . PHP_EOL;
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    public function displayWallet(): void
    {
        $transactions = $this->getTransactions();
        echo "Current balance is " . $transactions['balance'] . PHP_EOL;
        $holding = [];
        foreach ($transactions['transactions'] as $transaction) {
            $symbol = $transaction['symbol'];
            if (!isset($holding[$symbol])) {
                $holding[$symbol] = 0;
            }
            if ($transaction['type'] === 'purchase') {
                $holding[$symbol] += $transaction['amount'];
            } elseif ($transaction['type'] === "sell") {
                $holding[$symbol] -= $transaction['amount'];
            }
        }
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(["Symbol", "Amount"]);
        foreach ($holding as $symbol => $amount) {
            if ($amount > 0) {
                $table->addRow([$symbol, $amount]);
            }
        }
        $table->render();
    }

    public function displayTransactions(): void
    {
        $transactions = $this->getTransactions();
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(["Type", "Symbol", "Amount", "Price", "Value", "Time"]);
        foreach ($transactions['transactions'] as $transaction) {
            $table->addRow([
                ucfirst($transaction["type"]),
                $transaction['symbol'],
                $transaction['amount'],
                $transaction['price'],
                $transaction['cost'] ?? $transaction['value'],
                $transaction['time']
            ]);
        }
        $table->render();
    }
}