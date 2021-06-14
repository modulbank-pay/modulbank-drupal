<?php

namespace Drupal\commerce_payment_modulbank\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsVoidsInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

if (!class_exists("ModulbankHelper")) {
	include_once __DIR__ . '/../../../PluginForm/OffsiteRedirect/modulbanklib/ModulbankHelper.php';
	include_once __DIR__ . '/../../../PluginForm/OffsiteRedirect/modulbanklib/ModulbankReceipt.php';
}

/**
 * Provides the Modulbank offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "modulbank_redirect_checkout",
 *   label = @Translation("Modulbank Gateway"),
 *   display_label = @Translation("Modulbank"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_payment_modulbank\PluginForm\OffsiteRedirect\PaymentOffsiteForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "mastercard", "visa",
 *   },
 * )
 */
class OffsiteRedirect extends OffsitePaymentGatewayBase implements HasPaymentInstructionsInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface
{

	/**
	 * {@inheritdoc}
	 */
	public function defaultConfiguration()
	{
		return [
			'merchant'                => '',
			'secret_key'              => '',
			'test_secret_key'         => '',
			'success_url'             => '',
			'fail_url'                => '',
			'cancel_url'              => '',
			'sno'                     => 'usn_income_outcome',
			'product_vat'             => 'none',
			'delivery_vat'            => 'none',
			'payment_method'          => 'full_prepayment',
			'payment_object'          => 'commodity',
			'delivery_payment_object' => 'service',
			'logging'                 => false,
			'preauth'                 => 0,
			'max_log_size'            => 10,
			'show_custom_pm'          => 0,
			'card'                    => 0,
			'sbp'                     => 0,
			'googlepay'               => 0,
			'applepay'                => 0,
		] + parent::defaultConfiguration();
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildConfigurationForm(array $form, FormStateInterface $form_state)
	{
		$form = parent::buildConfigurationForm($form, $form_state);

		$form['merchant'] = [
			'#type'          => 'textfield',
			'#title'         => 'Мерчант',
			'#default_value' => $this->configuration['merchant'],
			'#required'      => true,
		];

		$form['secret_key'] = [
			'#type'          => 'textfield',
			'#title'         => 'Секретный ключ',
			'#default_value' => $this->configuration['secret_key'],
		];

		$form['test_secret_key'] = [
			'#type'          => 'textfield',
			'#title'         => 'Тестовый секретный ключ',
			'#default_value' => $this->configuration['test_secret_key'],
		];

		$sno_items = array(
			'osn'                => 'общая СН',
			'usn_income'         => 'упрощенная СН (доходы)',
			'usn_income_outcome' => 'упрощенная СН (доходы минус расходы)',
			'envd'               => 'единый налог на вмененный доход',
			'esn'                => 'единый сельскохозяйственный налог',
			'patent'             => 'патентная СН',
		);

		$form['sno'] = array(
			'#type'          => 'select',
			'#title'         => "Налогообложение",
			'#options'       => $sno_items,
			'#default_value' => $this->configuration['sno'],
		);

		$vat_items = array(
			'none'   => 'без НДС',
			'vat0'   => 'НДС по ставке 0%',
			'vat10'  => 'НДС чека по ставке 10%',
			'vat20'  => 'НДС чека по ставке 20%',
			'vat110' => 'НДС чека по расчетной ставке 10/110',
			'vat120' => 'НДС чека по расчетной ставке 20/120',
		);

		$form['product_vat'] = array(
			'#type'          => 'select',
			'#title'         => "Ставка НДС на товары",
			'#options'       => $vat_items,
			'#default_value' => $this->configuration['product_vat'],
		);

		$form['delivery_vat'] = array(
			'#type'          => 'select',
			'#title'         => "Ставка НДС на доставку",
			'#options'       => $vat_items,
			'#default_value' => $this->configuration['delivery_vat'],
		);

		$pm_items = array(
			'full_prepayment'    => 'полная предоплата',
			'partial_prepayment' => 'частичная предоплата',
			'advance'            => 'аванс',
			'full_payment'       => 'полный расчет',
			'partial_payment'    => 'частичный расчет и кредит',
			'credit'             => 'кредит',
			'credit_payment'     => 'выплата по кредиту',
		);

		$form['payment_method'] = array(
			'#type'          => 'select',
			'#title'         => "Признак способа расчета",
			'#options'       => $pm_items,
			'#default_value' => $this->configuration['payment_method'],
		);

		$po_items = array(
			'commodity'             => 'товар',
			'excise'                => 'подакцизный товар',
			'job'                   => 'работа',
			'service'               => 'услуга',
			'gambling_bet'          => 'ставка в азартной игре',
			'gambling_prize'        => 'выигрыш в азартной игре',
			'lottery'               => 'лотерейный билет',
			'lottery_prize'         => 'выигрыш в лотерею',
			'intellectual_activity' => 'результаты интеллектуальной деятельности',
			'payment'               => 'платеж',
			'agent_commission'      => 'агентское вознаграждение',
			'composite'             => 'несколько вариантов',
			'another'               => 'другое',
		);

		$form['payment_object'] = array(
			'#type'          => 'select',
			'#title'         => "Признак предмета расчета",
			'#options'       => $po_items,
			'#default_value' => $this->configuration['payment_object'],
		);

		$form['delivery_payment_object'] = array(
			'#type'          => 'select',
			'#title'         => "Признак предмета расчета на доставку",
			'#options'       => $po_items,
			'#default_value' => $this->configuration['delivery_payment_object'],
		);

		$form['preauth'] = array(
			'#type'          => 'select',
			'#title'         => "Предавторизация",
			'#options'       => [0 => 'Нет', 1 => 'Да'],
			'#default_value' => $this->configuration['preauth'],
		);

		$form['logging'] = [
			'#type'          => 'checkbox',
			'#title'         => $this->t('Logging'),
			'#default_value' => $this->configuration['logging'],
		];

		$form['show_custom_pm'] = array(
			'#type'          => 'checkbox',
			'#title'         => "Отображать определённые способы оплаты",
			'#default_value' => $this->configuration['show_custom_pm'],
		);
		$form['modulbank_custom_wrapper_begin'] = array(
			'#markup' => '<p>Для отображения отдельных методов оплаты установите галочку и выберите интересующие из списка</p>',
		);

		$form['card'] = array(
			'#type'          => 'checkbox',
			'#title'         => "По карте",
			'#default_value' => $this->configuration['card'],
		);

		$form['sbp'] = array(
			'#type'          => 'checkbox',
			'#title'         => "Система быстрых платежей",
			'#default_value' => $this->configuration['sbp'],
		);

		$form['googlepay'] = array(
			'#type'          => 'checkbox',
			'#title'         => "GooglePay",
			'#default_value' => $this->configuration['googlepay'],
		);

		$form['applepay'] = array(
			'#type'          => 'checkbox',
			'#title'         => "ApplePay",
			'#default_value' => $this->configuration['applepay'],
		);

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
	{
		parent::submitConfigurationForm($form, $form_state);
		if (!$form_state->getErrors()) {
			$values                                 = $form_state->getValue($form['#parents']);
			$this->configuration['merchant']        = $values['merchant'];
			$this->configuration['secret_key']      = $values['secret_key'];
			$this->configuration['test_secret_key'] = $values['test_secret_key'];
			$this->configuration['success_url']     = $values['success_url'];
			$this->configuration['fail_url']        = $values['fail_url'];
			$this->configuration['cancel_url']      = $values['cancel_url'];
			$this->configuration['logging']         = $values['logging'];
			$this->configuration['max_log_size']    = $values['max_log_size'];
			$this->configuration['preauth']         = $values['preauth'];
			$this->configuration['show_custom_pm']  = $values['show_custom_pm'];
			$this->configuration['card']            = $values['card'];
			$this->configuration['sbp']             = $values['sbp'];
			$this->configuration['googlepay']       = $values['googlepay'];
			$this->configuration['applepay']        = $values['applepay'];
		}
	}
/*
public function buildPaymentOperations(\Drupal\commerce_payment\Entity\PaymentInterface $payment) {
$payment_state = $payment->getState()->value;
$operations = [];
$operations['capture'] = [
'title' => 'Подтверить',
'page_title' => 'Подтвердить оплату',
'plugin_form' => 'receive-payment',
'access' => $payment_state == 'authorized',
];
$operations['refund'] = [
'title' => 'Возврат',
'page_title' => 'Возврат оплаты',
'plugin_form' => 'refund-payment',
'access' => in_array($payment_state, ['completed', 'authorized']),
];

return $operations;
}*/

	/**
	 * 	 * URL: http://site.domain/payment/notify/commerce_payment_modulbank
	 *
	 * {@inheritdoc}
	 *
	 * @param Request $request
	 * @return null|void
	 */
	public function onNotify(Request $request)
	{
		try
		{
			$transaction_id = $request->get('order_id');
			$order_storage  = $this->entityTypeManager->getStorage('commerce_order');
			/** @var $order OrderInterface */
			$order = $order_storage->load($transaction_id);
			if (is_null($order)) {
				throw new \Exception('Order not found');
			}
			if (!$this->checkSign($request)) {
				throw new \Exception('Sign error');
			}
			if (
				($request->request->get('state') === 'COMPLETE' && !$this->configuration['preauth'])
				|| $request->request->get('state') === 'AUTHORIZED'
			) {
				$order->set('state', 'completed');
				$order->save();
				$state           = $request->request->get('state') === 'AUTHORIZED' ? 'authorization' : 'completed';
				$payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
				$payment         = $payment_storage->create([
					'state'           => $state,
					'amount'          => $order->getTotalPrice(),
					'payment_gateway' => $this->entityId,
					'order_id'        => $order->id(),
					'remote_id'       => $request->request->get('transaction_id'),
					'remote_state'    => $request->request->get('state'),
				]);

				$payment->save();
			}

			echo 'SUCCESS';
		} catch (\PaymentGatewayException $e) {
			$this->log($e->getMessage());
			echo 'FAIL';
		}
	}

	public function capturePayment(PaymentInterface $payment, Price $amount = null)
	{
		$this->assertPaymentState($payment, ['authorization']);
		// If not specified, capture the entire amount.
		$amount      = $amount ?: $payment->getAmount();
		$order       = $payment->getOrder();
		$receiptJson = $this->getReceipt($payment);
		$data        = [
			'merchant'        => $this->configuration['merchant'],
			'amount'          => $amount->getNumber(),
			'transaction'     => $payment->getRemoteId(),
			'receipt_contact' => $order->getCustomer()->getEmail(),
			'receipt_items'   => $receiptJson,
			'unix_timestamp'  => time(),
			'salt'            => \ModulbankHelper::getSalt(),
		];
		$this->log($data, 'notice');
		$key      = $this->getKey();
		$response = \ModulbankHelper::capture($data, $key);
		$this->log($response, 'notice');
		$response = json_decode($response);
		if ($response->status !== 'ok') {
			throw new PaymentGatewayException($response->message, 1);
		}

		$payment->setState('completed');
		$payment->setAmount($amount);
		$payment->save();
	}

	public function voidPayment(PaymentInterface $payment)
	{
		$this->refundPayment($payment);
	}

	public function refundPayment(PaymentInterface $payment, Price $amount = null)
	{
		$this->assertPaymentState($payment, ['completed', 'authorization']);
		// If not specified, refund the entire amount.
		$amount = $amount ?: $payment->getAmount();
		$this->assertRefundAmount($payment, $amount);

		// Check if the Refund is partial or full.
		$old_refunded_amount = $payment->getRefundedAmount();
		$new_refunded_amount = $old_refunded_amount->add($amount);
		if ($new_refunded_amount->lessThan($payment->getAmount())) {
			$payment->setState('partially_refunded');
		} else {
			$payment->setState('refunded');
		}
		$this->log(array(
			'merchant'       => $this->configuration['merchant'],
			'amount'         => $amount->getNumber(),
			'transaction_id' => $payment->getRemoteId(),
		), 'info');
		$key      = $this->getKey();
		$response = \ModulbankHelper::refund($this->configuration['merchant'], $amount, $payment->getRemoteId(), $key);
		$this->log($response, 'info');
		$response = json_decode($response);
		if ($response->status !== 'ok') {
			throw new PaymentGatewayException($response->message, 1);
		}

		$payment->setRefundedAmount($new_refunded_amount);
		$payment->save();
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @param OrderInterface $order
	 * @param Request $request
	 */
	public function onReturn(OrderInterface $order, Request $request)
	{
		$_SESSION['modulbank_transaction_id'] = $request->get('transaction_id');
	}

	public function log($message, $type = 'error')
	{
		if ($this->configuration['logging']) {
			$message = var_export($message, true);
			if ($type == 'error') {
				\Drupal::logger('commerce_payment_modulbank')->error($message);
			} else {
				\Drupal::logger('commerce_payment_modulbank')->notice($message);
			}
		}
	}

	public function getReceipt(\Drupal\commerce_payment\Entity\PaymentInterface $payment)
	{
		$order       = $payment->getOrder();
		$amount      = $payment->getAmount()->getNumber();
		$receipt     = new \ModulbankReceipt($this->configuration['sno'], $this->configuration['payment_method'], $amount);
		$items       = $order->getItems();
		$adjustments = $order->getAdjustments();
		foreach ($items as $item) {
			$receipt->addItem(
				$item->getTitle(),
				$item->getUnitPrice()->getNumber(),
				$this->configuration['product_vat'],
				$this->configuration['payment_object'],
				$item->getQuantity()
			);
		}

		foreach ($adjustments as $adjustment) {
			if ($adjustment->getType() == 'shipping') {
				$receipt->addItem(
					$adjustment->getLabel(),
					$adjustment->getAmount()->getNumber(),
					$this->configuration['delivery_vat'],
					$this->configuration['delivery_payment_object']
				);
			}
		}
		return $receipt->getJson();
	}

	private function checkSign(Request $request)
	{
		$key       = $this->getKey();
		$signature = \ModulbankHelper::calcSignature($key, $_POST);
		return strcasecmp($signature, $request->get('signature')) === 0;
	}

	public function buildPaymentInstructions(\Drupal\commerce_payment\Entity\PaymentInterface $payment)
	{
		$html = '';
		if ($_SESSION['modulbank_transaction_id']) {
			$transactionResult = $this->getTransactionStatus($_SESSION['modulbank_transaction_id']);
			$paymentStatusText = "Ожидаем поступления средств";
			if (isset($transactionResult->status) && $transactionResult->status == "ok") {

				switch ($transactionResult->transaction->state) {
					case 'PROCESSING':$paymentStatusText = "В процессе";
						break;
					case 'WAITING_FOR_3DS':$paymentStatusText = "Ожидает 3DS";
						break;
					case 'FAILED':$paymentStatusText = "При оплате возникла ошибка";
						break;
					case 'COMPLETE':$paymentStatusText = "Оплата прошла успешно";
						break;
					case 'AUTHORIZED':$paymentStatusText = "Оплата прошла успешно";
						break;
					default:$paymentStatusText = "Ожидаем поступления средств";
				}
			}
			$html .= "Статус оплаты: " . $paymentStatusText;
		}
		return $html;
	}

	public function getKey()
	{
		if ($this->configuration['mode'] === 'test') {
			$key = $this->configuration['test_secret_key'];
		} else {
			$key = $this->configuration['secret_key'];
		}
		return $key;
	}

	private function getTransactionStatus($transaction)
	{
		$merchant = $this->configuration['merchant'];
		$this->log([$merchant, $transaction]);

		$key = $this->getKey();

		$result = \ModulbankHelper::getTransactionStatus(
			$merchant,
			$transaction,
			$key
		);
		$this->log($result, 'info');
		return json_decode($result);
	}

	public function buildPaymentOperations(PaymentInterface $payment)
	{
		$payment_state = $payment->getState()->value;
		$operations    = [];
		if ($this instanceof SupportsAuthorizationsInterface) {
			$operations['capture'] = [
				'title'       => $this->t('Подтвердить'),
				'page_title'  => $this->t('Подтверждение платежа'),
				'plugin_form' => 'capture-payment',
				'access'      => $payment_state == 'authorization',
			];
		}
		if ($this instanceof SupportsVoidsInterface) {
			$operations['void'] = [
				'title'       => $this->t('Отменить'),
				'page_title'  => $this->t('Отмена платежа'),
				'plugin_form' => 'void-payment',
				'access'      => $payment_state == 'authorization',
			];
		}
		if ($this instanceof SupportsRefundsInterface) {
			$operations['refund'] = [
				'title'       => $this->t('Возврат'),
				'page_title'  => $this->t('Возврат платежа'),
				'plugin_form' => 'refund-payment',
				'access'      => in_array($payment_state, ['completed', 'partially_refunded']),
			];
		}

		return $operations;
	}
}
