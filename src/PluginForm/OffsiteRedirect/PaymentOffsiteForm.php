<?php

namespace Drupal\commerce_payment_modulbank\PluginForm\OffsiteRedirect;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

if(!class_exists("ModulbankReceipt")) {
	include __DIR__.'/modulbanklib/ModulbankReceipt.php';
}
if(!class_exists("ModulbankHelper")) {
	include __DIR__.'/modulbanklib/ModulbankHelper.php';
}

class PaymentOffsiteForm extends BasePaymentOffsiteForm
{

	/**
	 * {@inheritdoc}
	 */
	public function buildConfigurationForm(array $form, FormStateInterface $form_state)
	{
		$form = parent::buildConfigurationForm($form, $form_state);

		/** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
		$payment = $this->entity;
		/** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
		$payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

		$configuration = $payment_gateway_plugin->getConfiguration();

		$amount = number_format($payment->getAmount()->getNumber(), 2, '.', '');
		$transaction_id = $payment->getOrderId();
		$order = $payment->getOrder();

		$redirect_url = 'https://pay.modulbank.ru/pay';
		$sysinfo = [
			'language' => 'PHP ' . phpversion(),
			'plugin'   => $this->getVersion(),
			'cms'      => 'Drupal '.\Drupal::VERSION,
		];

		$address = $order->getBillingProfile()->address->first();
		$name = $address->getGivenName() . ' ' . $address->getFamilyName();

		$data = [
			'merchant'        => $configuration['merchant'],
			'amount'          => $amount,
			'order_id'        => $transaction_id,
			'testing'         => $configuration['mode'] === 'test' ? 1 : 0,
			'preauth'         => $configuration['preauth'],
			'description'     => "Оплата заказа №" . $transaction_id,
			'success_url'     => $form['#return_url'],
			'fail_url'        => $form['#return_url'],
			'cancel_url'      => $form['#cancel_url'],//$configuration['cancel_url'],
			'callback_url'    => $payment_gateway_plugin->getNotifyUrl()->toString(),//$this->url->link('extension/payment/modulbank/callback', '', true),
			'client_name'     => $name,
			'client_email'    => $order->getCustomer()->getEmail(),
			'receipt_contact' => $order->getCustomer()->getEmail(),
			'receipt_items'   => $payment_gateway_plugin->getReceipt($payment),
			'unix_timestamp'  => time(),
			'sysinfo'         => json_encode($sysinfo),
			'salt'            => \ModulbankHelper::getSalt(),
		];

		if ($configuration['show_custom_pm']) {
			$methods = ['card', 'sbp', 'applepay', 'googlepay'];
			$methods = array_filter($methods, function ($method) use ($configuration) {
				return $configuration[$method];
			});
			$data['show_payment_methods'] = json_encode(array_values($methods));
		}

		$key = $payment_gateway_plugin->getKey();
		$data['signature'] = \ModulbankHelper::calcSignature($key, $data);
		$payment_gateway_plugin->log($data, 'info');
		$form = $this->buildRedirectForm($form, $form_state, $redirect_url, $data, 'post');
		return $form;
	}

	private function getVersion()
	{
		$moduleInfo = system_get_info('module','commerce_payment_modulbank');
		return $moduleInfo['version'];
	}

}
