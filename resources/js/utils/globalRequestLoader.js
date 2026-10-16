import { useUiStore } from '../stores/ui';

const loaderOptions = (config = {}) => {
  const method = (config.method || 'get').toUpperCase();
  const isDownload = config.responseType === 'blob';

  if (config.loaderTitle || config.loaderSubtitle) {
    return {
      title: config.loaderTitle,
      subtitle: config.loaderSubtitle,
    };
  }

  if (isDownload) {
    return {
      title: 'Preparing download',
      subtitle: 'Generating your file from the server',
    };
  }

  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    return {
      title: 'Processing request',
      subtitle: 'Saving your changes securely',
    };
  }

  return {
    title: 'Loading data',
    subtitle: 'Fetching the latest records from the server',
  };
};

export const startGlobalRequest = (config = {}) => {
  if (config.showGlobalLoader === false) return config;

  config.__globalLoader = true;
  useUiStore().startRequestLoading(loaderOptions(config));
  return config;
};

export const finishGlobalRequest = (config) => {
  if (config?.__globalLoader) {
    useUiStore().finishRequestLoading();
  }
};
