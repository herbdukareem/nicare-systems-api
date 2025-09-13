
export function useFormat() {
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
  };

  // format currency
  const formatCurrency = (amount) => {
    if (!amount) return '₦0';
    return new Intl.NumberFormat('en-NG', {
      style: 'currency',
      currency: 'NGN',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  };

  // format title to have proper case
 const formatTitle = (title) => {
  if (!title) return "";
  return title
    .toLowerCase()
    .split(" ")
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
};

  const formatLowercase = (title) => {
    return title.toLowerCase();
  };

  return {
    formatDate,
    formatCurrency,
    formatTitle,
    formatLowercase,
  };
}