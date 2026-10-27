/**
 * Hisabio - Ledger Interactivity & Live Calculations
 */

const HisabioLedger = {
  // Live calculation of projected balance on entry screens
  initCalculator: function(currentBalance, isCustomer = true, entryType = 'credit') {
    const amountInput = document.getElementById('amount');
    const previewEl = document.getElementById('projected-balance');
    if (!amountInput || !previewEl) return;

    function update() {
      const val = parseFloat(amountInput.value) || 0;
      let newBalance = currentBalance;

      if (isCustomer) {
        // Customer: credit increases balance (they owe more), payment reduces balance
        if (entryType === 'credit') {
          newBalance += val;
        } else if (entryType === 'payment') {
          newBalance -= val;
        }
      } else {
        // Supplier: purchase increases payable (we owe more), payment reduces payable
        if (entryType === 'purchase') {
          newBalance += val;
        } else if (entryType === 'payment') {
          newBalance -= val;
        }
      }

      const formatted = Math.abs(newBalance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      let statusText = '';
      if (newBalance > 0) {
        statusText = isCustomer ? `Customer will owe PKR ${formatted}` : `You will owe supplier PKR ${formatted}`;
        previewEl.className = 'font-bold text-credit';
      } else if (newBalance < 0) {
        statusText = isCustomer ? `You will owe customer PKR ${formatted} (Advance)` : `Supplier will owe you PKR ${formatted} (Advance)`;
        previewEl.className = 'font-bold text-payment';
      } else {
        statusText = 'Account balance will be completely settled (PKR 0.00)';
        previewEl.className = 'font-bold text-payment';
      }

      previewEl.textContent = statusText;
    }

    amountInput.addEventListener('input', update);
    update();
  },

  // Instant card/item filter
  initInstantSearch: function(inputSelector, itemSelector, textSelector) {
    const input = document.querySelector(inputSelector);
    if (!input) return;

    input.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      const items = document.querySelectorAll(itemSelector);
      let visibleCount = 0;

      items.forEach(item => {
        const textTarget = textSelector ? item.querySelector(textSelector) : item;
        const text = textTarget ? textTarget.textContent.toLowerCase() : '';
        if (text.includes(q)) {
          item.style.display = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      const emptyEl = document.getElementById('empty-search-state');
      if (emptyEl) {
        emptyEl.style.display = visibleCount === 0 ? 'block' : 'none';
      }
    });
  }
};
