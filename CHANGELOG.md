# Yii Caching Library - Redis Handler Change Log

## 2.0.1 under development

- Enh #30: Remove unneeded casting to array in private method `RedisCache::iterableToArray()` (@vjik)
- Chg #38: Bump minimum PHP version to `8.1` (@s1lver)
- Chg #40: Make `RedisCache::$client` readonly (@s1lver)

## 2.0.0 July 10, 2023

- Chg #5: Raise the minimum `psr/simple-cache` version to `^2.0|^3.0` and the minimum PHP version to `^8.0` (@dehbka)
- Chg #7: Raise the `predis/predis` version to `^2.1` (@s1lver)
- Enh #7: Cluster support added (@s1lver)

## 1.0.0 November 07, 2021

- Initial release.
