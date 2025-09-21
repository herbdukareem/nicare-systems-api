export default {
  basePath: "/",

  get(key) {
    return localStorage.getItem(key) || null; 
  },

  set(key, val) {
    localStorage.setItem(key, val);
  },

  remove(key) {
    localStorage.removeItem(key);
  },

  getPath(path) {
    return this.basePath + (path.startsWith('/') ? '' : '/') + path; 
  },
};
