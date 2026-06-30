window.formatPrice = function (value) {
  if (value == null || isNaN(value)) value = 0;
  return Number(value).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
