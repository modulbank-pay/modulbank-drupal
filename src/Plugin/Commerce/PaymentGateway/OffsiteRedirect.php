<?php

namespace Drupal\commerce_payment_modulbank\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderStorage;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface;

if(!class_exists("ModulbankHelper")) {
	include_once(__DIR__.'/../../../PluginForm/OffsiteRedirect/modulbanklib/ModulbankHelper.php');
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
class OffsiteRedirect extends OffsitePaymentGatewayBase implements HasPaymentInstructionsInterface
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
			'max_log_size'            => 10,
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
/*
		$form['success_url'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Success url'),
			'#default_value' => $this->configuration['success_url'],
		];

		$form['fail_url'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Fail url'),
			'#default_value' => $this->configuration['fail_url'],
		];

		$form['cancel_url'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Cancel url'),
			'#default_value' => $this->configuration['cancel_url'],
		];*/

		$form['logging'] = [
			'#type'          => 'checkbox',
			'#title'         => $this->t('Logging'),
			'#default_value' => $this->configuration['logging'],
		];
/*
		$form['max_log_size'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Max log size(mb)'),
			'#default_value' => $this->configuration['max_log_size'],
		];*/

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
		}
	}

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
			if ($request->request->get('state') === 'COMPLETE') {
				$order->set('state','completed');
				$order->save();
				$payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
				$payment         = $payment_storage->create([
					'state'           => 'completed',
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

	/**
	 * Page for MNT_SUCCESS_URL
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

	public function log($message, $type = 'error'){
		if($this->configuration['logging']) {
			$message = var_export($message, true);
			if ($type == 'error') {
				\Drupal::logger('commerce_payment_modulbank')->error($message);
			} else {
				\Drupal::logger('commerce_payment_modulbank')->notice($message);
			}
		}
	}

	private function checkSign(Request $request) {
		$key       = $this->configuration['mode'] == 'test' ? $this->configuration['test_secret_key'] : $this->configuration['secret_key'];
		$signature = \ModulbankHelper::calcSignature($key, $_POST);
		return strcasecmp($signature, $request->get('signature')) === 0;
	}

	public function buildPaymentInstructions(\Drupal\commerce_payment\Entity\PaymentInterface $payment) {
		$html = '';
		if($_SESSION['modulbank_transaction_id']){
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
					default:$paymentStatusText = "Ожидаем поступления средств";
				}
			}
			$html .= "Статус оплаты: ".$paymentStatusText;
		}
		return $html;
	}

	private function getTransactionStatus($transaction)
	{
		$merchant = $this->configuration['merchant'];
		$this->log([$merchant, $transaction]);

		$key = $this->configuration['mode'] == 'test' ? $this->configuration['test_secret_key'] : $this->configuration['secret_key'];

		$result = \ModulbankHelper::getTransactionStatus(
			$merchant,
			$transaction,
			$key
		);
		$this->log($result, 'info');
		return json_decode($result);
	}
}
