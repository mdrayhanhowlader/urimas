<div class="order-section" id="orderSection">
  <div class="order-card">
    <div class="order-header">
      <h2><i class="fas fa-shopping-bag" style="margin-right:8px"></i>Your Order</h2>
      <button class="order-back-btn" onclick="closeOrderForm()">← Change</button>
    </div>

    <div class="order-summary">
      <div class="order-summary-title">Selected Items</div>
      <div id="orderItemsList"></div>
      <div class="order-totals">
        <div class="order-total-row">
          <span>Subtotal</span>
          <span id="summaryBookPrice">৳0</span>
        </div>
        <div class="order-total-row">
          <span>Delivery</span>
          <span id="summaryDelivery">৳—</span>
        </div>
        <div class="order-total-row grand">
          <span>Total</span>
          <span id="summaryTotal">—</span>
        </div>
      </div>
    </div>

    <div class="form-body">
      <form id="orderForm" novalidate>

        <div class="form-grid">
          <div class="form-group">
            <label for="fname">Full Name *</label>
            <input type="text" id="fname" placeholder="Your full name" required autocomplete="name" inputmode="text">
          </div>
          <div class="form-group">
            <label for="fphone">Phone Number *</label>
            <div class="phone-wrap">
              <span class="phone-code" id="phoneCodeDisplay"><?= htmlspecialchars($country_code) ?></span>
              <input type="tel" id="fphone" placeholder="01XXXXXXXXX" required autocomplete="tel" inputmode="numeric">
            </div>
          </div>
          <div class="form-group full">
            <label for="faddress">Full Address *</label>
            <textarea id="faddress" placeholder="House no., road, area, district" required autocomplete="street-address"></textarea>
          </div>
          <div class="form-group">
            <label for="farea">Delivery Area *</label>
            <select id="farea" required onchange="updateTotals()">
              <option value="">Choose</option>
              <option value="dhaka" id="optDhaka">Dhaka</option>
              <option value="outside" id="optOutside">Outside Dhaka</option>
            </select>
            <span class="hint" id="deliveryHint" style="font-size:.73rem;color:var(--muted)"></span>
            <div id="deliveryCostBox" style="margin-top:8px;background:var(--accent-light);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
              <span style="font-size:.8rem;color:var(--muted);font-weight:600">Delivery Fee</span>
              <span style="font-size:1.05rem;font-weight:800;color:var(--accent)" id="deliveryCostDisplay">৳—</span>
            </div>
          </div>
          <div class="form-group">
            <label>Payment Method *</label>
            <div class="payment-toggle">
              <button type="button" class="pay-btn" id="btnCod" onclick="setPayment('cod')">
                <i class="fas fa-money-bill-wave"></i> Cash on Delivery
              </button>
              <button type="button" class="pay-btn active" id="btnBkash" onclick="setPayment('bkash')">
                <i class="fas fa-mobile-alt"></i> bKash
              </button>
            </div>
          </div>
        </div>

        <div id="bkashManualBox" style="margin-top:12px">
          <div id="bkashQrBlock"></div>
          <div class="bkash-info" id="bkashInfoSimple" style="margin-top:8px">
            <div>
              <div class="bkash-label">Send to bKash number</div>
              <div class="bkash-number" id="bkashNumber">Loading...</div>
            </div>
            <button type="button" class="copy-btn" onclick="copyBkash()">Copy</button>
          </div>
        </div>

        <div id="bkashApiBox" style="display:none;margin-top:12px">
          <button type="button" class="submit-btn" id="bkashPayBtn" onclick="initiateBkashPayment()"
                  style="background:linear-gradient(135deg,#f0166c,#e2136e);margin-top:0;padding:13px">
            <i class="fas fa-mobile-alt" style="margin-right:8px"></i>Pay with bKash
          </button>
          <p style="font-size:.75rem;color:var(--muted);text-align:center;margin-top:8px">
            You'll be redirected to bKash payment page
          </p>
        </div>

        <div class="transaction-field show form-group" id="txnField">
          <label for="ftxn">Transaction ID *</label>
          <input type="text" id="ftxn" placeholder="bKash Transaction ID">
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">
          <i class="fas fa-paper-plane" style="margin-right:8px"></i>Place Order
        </button>

      </form>
    </div>
  </div>
</div>
