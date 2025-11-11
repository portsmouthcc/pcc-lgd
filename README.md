# PCC LGD

[![Tests](https://github.com/portsmouthcc/pcc-lgd/actions/workflows/test.yml/badge.svg)](https://github.com/portsmouthcc/pcc-lgd/actions/workflows/test.yml)

## Getting started

```
git clone git@github.com:portsmouthcc/pcc-lgd.git
cd pcc-lgd
ddev start
ddev composer install
```

## Check theme coding standards

We can apply standard Drupal coding standards to the theme. First, install core dependencies:

```
cd web/core
ddev npm install
```

Then install theme dependencies (fron the root):

```
cd web/themes/custom/pcc
ddev npm install
```

Then you can run all the tests and attempt to automatically fix errors with:

```
ddev npm start
ddev npm run start:fix
```
