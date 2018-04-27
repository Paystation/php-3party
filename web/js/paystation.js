// provides functions to talk to the JSON api (paystation_ajax.php)
(function () {
	var _ajaxUrl = 'paystation_ajax.php'; // See createTransaction() and getTransaction() to change how fields are posted
	var _pollingInterval;

	function hex (x) {
		return ("0" + parseInt(x).toString(16)).slice(-2);
	}

	function rgb2hex(rgb) {
		if (!rgb || typeof rgb != 'string') {
			return '';
		}
		if (rgb.startsWith('#')) {
			return str.substr(1, 6);
		}
		rgb = rgb.match(/^\w*\((\d+),\s?(\d+),\s?(\d+)(.)*?\)$/);
		return hex(rgb[1]) + hex(rgb[2]) + hex(rgb[3]);
	}

	function tryToFindABackgroundColour(element) {
		if (!element) {
			return null;
		}
		var colour = '000000';
		for (var i = 0; element && i < 10 && colour == '000000'; i++) {
			colour = rgb2hex(window.getComputedStyle(element, null).backgroundColor);
			element = element.parentElement;
		}
		if (colour == '000000') {
			colour = rgb2hex(window.getComputedStyle(document.body, null).backgroundColor);
		}
		return colour == '000000' ? null : colour;
	}

	var Paystation = {
		// ajax e.g. post('your/url', 'transactionId=123&userid=123', function (err, data) { /*check for error and handle data*/ });
		post: function(url, postData, callback) {
			var request = new XMLHttpRequest();
			request.open('POST', url, true);
			request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

			request.onreadystatechange = function() {
				if (this.readyState === 4) {
					if (this.status >= 200 && this.status < 400) {
						callback(null, JSON.parse(this.responseText));
					}
					else {
						callback(new Error("Request failed. " + this.status));
					}
				}
			};

			request.send(postData);
			request = null;
		},

		// callback(error, newTransaction)
		// useful fields on trasaction:
		// trasaction.digitalOrderUrl = url for the iframe
		// trasaction.transactionId = id used for future lookups to see if the transaction has been completed
		createTransaction: function(amount, callback) {
			this.post(
				_ajaxUrl,
				'method=make_transaction&amount=' + amount,
				function(err, txn) {
					if (err || txn.hasError ||  txn.errorCode > 0 || !txn.digitalOrderUrl) {
						return callback(new Error("Error occurred while making transaction: " + (err || txn.errorMessage)));
					}

					callback(null, txn);
				}
			)
		},

		// callback(error, transaction)
		// transaction.hasError is for all errors regardless if they come from paystation or us, which could happen before the transaction completes.
		// transaction.errorCode is a paystation response which is set after a transaction is complete. A negative error code means no error code has been returned.
		getTransaction: function(transactionId, callback) {
			this.post(
				_ajaxUrl,
				'method=get_transaction&transaction_id=' + transactionId,
				function(err, transaction) {
					if (err) {
						return callback(err);
					}

					callback(null, transaction);
				}
			);
		},

		// Since we don't know when the transaction is complete we can use this to constantly check its status.
		pollTransactionDetails: function(transactionId, callback) {
			this.stopPolling();

			_pollingInterval = window.setInterval(function () {
				Paystation.getTransaction(transactionId, callback);
			}, 1337);
		},

		// This is only designed to poll for one transaction at a time. It will need to be reworked if you want the user to be making multiple payments on the same screen.
		stopPolling: function() {
			if (_pollingInterval) {
				window.clearInterval(_pollingInterval);
			}
		},

		createPaymentFrame: function(parentElement, paymentURL, onLoad, changeBackgroundColour) {
			var paymentFrame = document.createElement('iframe');
			if (changeBackgroundColour) {
				var colour = tryToFindABackgroundColour(parentElement);
				if (colour) {
					paymentURL += '&b=' + colour;
				}
			}
			paymentFrame.src = paymentURL;
			parentElement.appendChild(paymentFrame);
			this.bindFrameLoadEvent(paymentFrame, onLoad);
			return paymentFrame;
		},

		closePaymentFrame: function(paymentFrame) {
			if (paymentFrame) {
				paymentFrame.parentNode.removeChild(paymentFrame);
				paymentFrame = false;
			}
		},

		// This is used to detect when the frame is redirected. If you are not using redirects then you wont need to use this.
		bindFrameLoadEvent: function(frame, onLoad) {
			if (navigator.userAgent.indexOf("MSIE") > -1 && !window.opera) {
				frame.addEventListener('readystatechange', function() {
					if (frame.readyState == "complete") {
						onLoad(frame);
					}
				});
			}
			else {
				frame.addEventListener('load', function() {
					onLoad(frame);
				});
			}
		},

		// Most browsers do not allow manipulating the contents of an iframe if it is in a different domain.
		// This can be used to test if the client has been redirected back to your website after making the payment in paystation.
		canAccessIFrame: function(iframe) {
			var html = null;
			try {
				var doc = iframe.contentDocument || iframe.contentWindow.document;
				html = doc.body.innerHTML;
			}
			catch (err) {}
			return (html !== null);
		}
	};

	window.Paystation = Paystation;
})();