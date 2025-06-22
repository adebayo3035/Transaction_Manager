
function maskCardNumber(cardNumber) {
    const visibleDigits = 4;
    const maskedSection = cardNumber.slice(0, -visibleDigits).replace(/\d/g, '*');
    return maskedSection + cardNumber.slice(-visibleDigits);
}

function getCardBrandImage(cardNumber) {
    const visa = /^4/;
    const mastercard = /^5[1-5]/;
    if (visa.test(cardNumber)) return '../../images/visa.png';
    if (mastercard.test(cardNumber)) return '../../images/mastercard.png';
    return '../../images/chip2.png';
}
document.addEventListener('DOMContentLoaded', () => {

    // Insert the form into the DOM
    const form = document.createElement('form');
    form.id = 'cardForm';
    form.method = 'POST';
    form.action = '../v1/add_funds.php';
    form.style.display = 'none';
    form.innerHTML = `
        <input type="hidden" name="card_number" id="cardNumberInput">
        <input type="hidden" name="cvv" id="cvvInput">
    `;
    document.body.appendChild(form);

    // Fetch and display orders summary
    fetch('../v2/get_cards.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message);
            const container = document.getElementById('card-container');
            container.innerHTML = '';

            data.cards.forEach(card => {
                const cardElement = document.createElement('div');
                cardElement.className = 'card';

                const maskedCardNumber = maskCardNumber(card.card_number);
                const brandImg = getCardBrandImage(card.card_number);

                cardElement.innerHTML = `
        <div class="card-content">
          <div class="card-front">
            <div class="card-number">${maskedCardNumber}</div>
            <div class="bank-name">${card.bank_name}</div>
            <div class="expiry-date">${card.expiry_date}</div>
            <img src="${brandImg}" alt="Card Brand" class="brand-icon">
          </div>
          <div class="card-back">
            <div class="magnetic-stripe"></div>
            <div class="card-holder">Name: ${card.card_holder}</div>
            <div class="cvv" style="display: none;">CVV: ${card.cvv}</div>
          </div>
        </div>
      `;

                // Click toggles flip
                cardElement.addEventListener('click', () => {
                    cardElement.classList.toggle('flipped');
                    const cvvEl = cardElement.querySelector('.cvv');
                    if (cardElement.classList.contains('flipped')) {
                        cvvEl.style.display = 'block';
                    } else {
                        cvvEl.style.display = 'none';
                    }
                });

                container.appendChild(cardElement);
            });
        })
        .catch(err => {
            console.error("Error loading cards:", err.message);
            document.getElementById('card-container').innerHTML = `<p class="text-danger">Failed to load cards</p>`;
        });
});

