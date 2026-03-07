<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
AUTO BADGE + EMAIL NOTICE
----------------------------------------------------------*/

function baby_render_paid_badge_and_email_notice($order) {

    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }

    $st = $order->get_status();

    if (in_array($st, ['processing', 'completed', 'pending', 'on-hold'], true)) {

        if ($st === 'completed')      $label = 'Order completed ✓';
        elseif ($st === 'processing') $label = 'Order is processing ✓';
        elseif ($st === 'pending')    $label = 'Awaiting payment (Pending)';
        else                          $label = 'On hold';

        echo '<div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">';

        $cls = in_array($st, ['processing', 'completed'], true) ? 'is-success' : 'is-info';
        echo '<span class="baby-vp-badge ' . esc_attr($cls) . '"><span class="dot"></span> ' . esc_html($label) . '</span>';

        $ts = (int)$order->get_meta('_baby_vp_email_notice_ts');

        if ($ts > 0) {
            if (time() - $ts <= 600) {
                echo '<span class="baby-vp-badge is-info"><span class="dot"></span> Order notification sent to your email ✓</span>';
            }

            $order->delete_meta_data('_baby_vp_email_notice_ts');
            $order->save();
        }

        echo '</div>';
    }
}


/* ---------------------------------------------------------
SHOW VERIFY BUTTON (ORDER PAGE)
----------------------------------------------------------*/

add_action('woocommerce_order_details_after_order_table', function($order) {

    if (!$order) return;

    baby_render_paid_badge_and_email_notice($order);

    echo '<div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:10px; align-items:center;">';

    // ALWAYS show track another order
    echo '<a href="'.esc_url(home_url('/track-orders/')).'" class="button" style="margin-right:8px; margin-bottom:10px;">Track another order</a>';

    // Verify button only for cancelled OR pending Paystack
    if (in_array($order->get_status(), ['cancelled','pending'], true) && $order->get_payment_method() === 'paystack') {

        $order_id  = $order->get_id();
        $order_key = $order->get_order_key();
        $nonce     = wp_create_nonce('baby_verify_' . $order_id . '_' . $order_key);

        echo '<button class="button baby_verify_payment" style="margin-bottom:10px;"
            data-order="' . esc_attr($order_id) . '"
            data-key="' . esc_attr($order_key) . '"
            data-nonce="' . esc_attr($nonce) . '">
            Verify payment
        </button>';

        echo '<span class="baby-vp-badge"></span>';
    }

    echo '</div>';

}, 20);


/* ---------------------------------------------------------
FRONTEND UI
----------------------------------------------------------*/

add_action('wp_footer', function () {
?>
<style>
.baby-vp-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:6px 10px;
  border-radius:999px;
  font-size:13px;
  font-weight:600;
  border:1px solid transparent;
  margin-left:12px;
  margin-top:10px;
}
.baby-vp-badge.is-info{
  background:#f3f4f6;
  border-color:#e5e7eb;
  color:#111827;
}
.baby-vp-badge.is-success{
  background:#ecfdf5;
  border-color:#a7f3d0;
  color:#065f46;
}
.baby-vp-badge.is-error{
  background:#fef2f2;
  border-color:#fecaca;
  color:#991b1b;
}
.baby-vp-badge .dot{
  width:10px;
  height:10px;
  border-radius:50%;
  background:currentColor;
}
.button.baby_verify_payment.is-loading{
  pointer-events:none;
  opacity:.7;
}

@media (max-width: 767px){
  .baby-vp-badge{
    margin-left:0;
    margin-right:0;
  }

  .woocommerce a.button,
  .woocommerce button.button,
  .woocommerce input.button,
  .woocommerce .button.baby_verify_payment{
    margin-bottom:10px;
  }
}
</style>

<script>
document.addEventListener("click", function(e){

  let btn = e.target.closest(".baby_verify_payment");
  if(!btn) return;

  let order = btn.dataset.order;
  let key   = btn.dataset.key;
  let nonce = btn.dataset.nonce;

  let badge = btn.parentNode.querySelector(".baby-vp-badge");

  btn.classList.add("is-loading");

  if(badge){
    badge.className = "baby-vp-badge is-info";
    badge.innerHTML = '<span class="dot"></span> Verifying...';
  }

  let form = new FormData();
  form.append("action", "baby_verify_payment");
  form.append("order_id", order);
  form.append("order_key", key);
  form.append("nonce", nonce);

  fetch("<?php echo esc_js(admin_url('admin-ajax.php')); ?>", {
    method: "POST",
    body: form,
    credentials: "same-origin"
  })
  .then(r => r.json())
  .then(data => {

    btn.classList.remove("is-loading");

    if(data && data.success){

      if(badge){
        badge.className = "baby-vp-badge is-success";
        badge.innerHTML = '<span class="dot"></span> Verified ✓';
      }

      btn.disabled = true;

      let extra1 = document.createElement("span");
      extra1.className = "baby-vp-badge is-success";
      extra1.innerHTML = '<span class="dot"></span> Order changed to Processing ✓';
      btn.parentNode.appendChild(extra1);

      let extra2 = document.createElement("span");
      extra2.className = "baby-vp-badge is-info";
      extra2.innerHTML = '<span class="dot"></span> Order notification sent ✓';
      btn.parentNode.appendChild(extra2);

    } else {

      let msg = (data && data.data && data.data.message)
        ? data.data.message
        : "Verification failed.";

      if(badge){
        badge.className = "baby-vp-badge is-error";
        badge.innerHTML = '<span class="dot"></span> ' + msg;
      }
    }

  })
  .catch(() => {

    btn.classList.remove("is-loading");

    if(badge){
      badge.className = "baby-vp-badge is-error";
      badge.innerHTML = '<span class="dot"></span> Network error';
    }

  });

});
</script>
<?php
});

add_action('wp_footer', function () {
  if ( ! is_page('track-orders') ) return;
?>
<style>
.woocommerce .woocommerce-order-tracking,
.woocommerce .woocommerce-order-tracking p,
.woocommerce .woocommerce-order-tracking label,
.woocommerce .woocommerce-order-tracking .woocommerce-info {
  color:#000 !important;
}
</style>

<script>
async function babyTrackByPaystack(ref, email){
  const errBox = document.querySelector(".baby-ps-track-error");
  if(errBox) errBox.textContent = "Checking details...";

  const fd = new FormData();
  fd.append("action","baby_track_by_paystack");
  fd.append("reference",ref);
  fd.append("email",email);

  try{
    const res = await fetch("<?php echo esc_js(admin_url('admin-ajax.php')); ?>",{
      method:"POST",
      body:fd,
      credentials:"same-origin"
    });

    const data = await res.json();

    if(data && data.success && data.data && data.data.orderid && data.data.email){

      const form = document.querySelector("form.woocommerce-form-track-order");
      if(!form){
        if(errBox) errBox.textContent = "Tracking form not found.";
        return false;
      }

      const orderIdInput = form.querySelector('input[name="orderid"]');
      const emailInput   = form.querySelector('input[name="order_email"]');

      if(orderIdInput) orderIdInput.value = data.data.orderid;
      if(emailInput)   emailInput.value   = data.data.email;

      if(errBox) errBox.textContent = "";

      form.dataset.psAutofill = "1";
      form.submit();
      return true;
    }

    if(errBox) errBox.textContent = (data && data.data && data.data.message) ? data.data.message : "Could not find order.";
    return false;

  }catch(e){
    if(errBox) errBox.textContent = "Network error. Try again.";
    return false;
  }
}

document.addEventListener("DOMContentLoaded", function(){
  // Change Woo button text
  const btn = document.querySelector("form.woocommerce-form-track-order button[type='submit']");
  if(btn) btn.textContent = "Track order";

  // Change order ID placeholder
  const orderIdField = document.querySelector('input[name="orderid"]');
  if(orderIdField) orderIdField.placeholder = "e.g. 12345";

  const form = document.querySelector("form.woocommerce-form-track-order");
  if(!form) return;

  const wooEmail = form.querySelector('input[name="order_email"]');
  if(wooEmail) wooEmail.placeholder = "eg. your_email@gmail.com";

  const orderRow = form.querySelector('p.form-row input[name="orderid"]')?.closest("p.form-row");
  if(orderRow && !form.querySelector(".baby-track-normal-title")){
    const title = document.createElement("p");
    title.className = "baby-track-normal-title";
    title.textContent = "Track order using Order Number & Email";
    title.style.margin = "0 0 10px";
    title.style.fontWeight = "600";
    form.insertBefore(title, orderRow);
  }

});

document.addEventListener("submit", async function(e){

  if(e.target.dataset.psAutofill === "1"){
    delete e.target.dataset.psAutofill;
    return;
  }

  const form = e.target;
  if(!form.matches("form.woocommerce-form-track-order")) return;

  const orderIdInput = form.querySelector('input[name="orderid"]');
  const emailInput   = form.querySelector('input[name="order_email"]');

  const psEmail = form.querySelector('input[name="baby_ps_email"]');
  const psRef   = form.querySelector('input[name="baby_ps_reference"]');

  const orderid = orderIdInput ? orderIdInput.value.trim() : "";
  const email   = emailInput ? emailInput.value.trim() : "";

  const pEmail  = psEmail ? psEmail.value.trim() : "";
  const ref     = psRef ? psRef.value.trim() : "";

  // A) Normal Woo tracking
  if(orderid && email) return;

  // B) Paystack tracking
  if(ref && pEmail){
    e.preventDefault();
    await babyTrackByPaystack(ref, pEmail);
    return;
  }

  // C) Invalid / incomplete
  e.preventDefault();

  if((orderid && !email) || (!orderid && email)){
    alert("Use Order ID + Billing email together.");
    return;
  }
  if((ref && !pEmail) || (!ref && pEmail)){
    alert("Use Paystack reference + Billing email together.");
    return;
  }

  alert("Enter Order ID + Billing email, OR Paystack reference + Billing email.");
});
</script>
<?php
});