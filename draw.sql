/*
 Navicat Premium Data Transfer

 Source Server         : sam
 Source Server Type    : MySQL
 Source Server Version : 50724
 Source Host           : 47.110.44.33:3306
 Source Schema         : draw

 Target Server Type    : MySQL
 Target Server Version : 50724
 File Encoding         : 65001

 Date: 29/12/2018 16:08:40
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for active
-- ----------------------------
DROP TABLE IF EXISTS `active`;
CREATE TABLE `active`  (
  `active_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '活动id',
  `active_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '活动名称',
  `start_time` int(11) NOT NULL DEFAULT 0 COMMENT '活动开始时间',
  `end_time` int(11) NOT NULL DEFAULT 0 COMMENT '活动结束时间',
  `must_award` int(6) NOT NULL DEFAULT 3 COMMENT '连续签到N天必中',
  `enable` tinyint(2) NOT NULL DEFAULT 0 COMMENT '是否启用  0 禁用 1 启用',
  `image` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '活动图片URL',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
  PRIMARY KEY (`active_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 61 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '活动表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for active_prize
-- ----------------------------
DROP TABLE IF EXISTS `active_prize`;
CREATE TABLE `active_prize`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `active_id` int(11) NOT NULL DEFAULT 0 COMMENT '活动id',
  `prize_id` int(11) NOT NULL DEFAULT 0 COMMENT '奖品id',
  `prize_name` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '奖品名称',
  `active_prize_number` int(8) NOT NULL DEFAULT 0 COMMENT '活动奖品数量',
  `active_surplus_number` int(8) NOT NULL DEFAULT 0 COMMENT '活动奖品余量',
  `every_day_number` int(8) NOT NULL DEFAULT 0 COMMENT '每天奖品数量',
  `chance` int(4) NOT NULL DEFAULT 0 COMMENT '中将概率 0~100的整数',
  `award_level` tinyint(3) NOT NULL DEFAULT 0 COMMENT '奖项  0  谢谢惠顾  1  一等奖  2  二等奖  3  三等奖 ......',
  `must_award_prize` tinyint(2) NOT NULL DEFAULT 0 COMMENT '是否为必中奖品  0 不是 1 是',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 26 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '活动-奖品中间表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for admin_user
-- ----------------------------
DROP TABLE IF EXISTS `admin_user`;
CREATE TABLE `admin_user`  (
  `admin_user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '后台用户id',
  `username` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '用户名称',
  `password` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '用户密码',
  `enable` tinyint(2) NOT NULL DEFAULT 0 COMMENT '是否启用  0 启用  1 禁用',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  `deleted_at` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
  PRIMARY KEY (`admin_user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '后台用户表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for award
-- ----------------------------
DROP TABLE IF EXISTS `award`;
CREATE TABLE `award`  (
  `award_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '中奖id',
  `wx_user_id` int(11) NOT NULL DEFAULT 0 COMMENT '微信用户id',
  `prize_id` int(11) NOT NULL DEFAULT 0 COMMENT '奖品id',
  `prize_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '奖品名称',
  `active_id` int(11) NOT NULL DEFAULT 0 COMMENT '活动id',
  `award_level` tinyint(3) NOT NULL DEFAULT 0 COMMENT '奖项',
  `business_hall_id` int(11) NOT NULL DEFAULT 0 COMMENT '营业厅id',
  `business_hall_name` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '营业厅名称',
  `exchange_code` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '兑换码',
  `is_exchange` tinyint(2) NOT NULL DEFAULT 0 COMMENT '是否兑换  0 未兑换  1 已兑换',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间（中奖时间）',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  `expire_time` int(11) NOT NULL DEFAULT 0 COMMENT '兑换码过期时间',
  `exchange_time` int(11) NOT NULL DEFAULT 0 COMMENT '兑换时间',
  `deadline` int(11) NOT NULL DEFAULT 0 COMMENT '兑奖结束时间',
  PRIMARY KEY (`award_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '中奖表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for business_hall
-- ----------------------------
DROP TABLE IF EXISTS `business_hall`;
CREATE TABLE `business_hall`  (
  `business_hall_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '营业厅id',
  `business_hall_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '营业厅名称',
  `province` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '省',
  `area` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '区',
  `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '详细地址',
  `bank` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  `deleted_at` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
  PRIMARY KEY (`business_hall_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 762 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '营业厅表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for business_hall_prize
-- ----------------------------
DROP TABLE IF EXISTS `business_hall_prize`;
CREATE TABLE `business_hall_prize`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `business_hall_id` int(11) NOT NULL DEFAULT 0 COMMENT '营业厅id',
  `prize_id` int(11) NOT NULL DEFAULT 0 COMMENT '奖品id',
  `prize_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '奖品名称',
  `business_prize_number` int(8) NOT NULL DEFAULT 0 COMMENT '营业厅奖品余量',
  `business_surplus_number` int(8) NOT NULL DEFAULT 0 COMMENT '营业厅奖品总量',
  `lock_prize_number` int(8) NOT NULL DEFAULT 0 COMMENT '锁定奖品数量',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '营业厅-奖品中间表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for exchange_code
-- ----------------------------
DROP TABLE IF EXISTS `exchange_code`;
CREATE TABLE `exchange_code`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wx_user_id` int(11) NOT NULL DEFAULT 0 COMMENT '微信用户id',
  `award_id` int(11) NOT NULL DEFAULT 0 COMMENT '中奖id',
  `business_hall_id` int(11) NOT NULL DEFAULT 0 COMMENT '营业厅id',
  `prize_id` int(11) NOT NULL DEFAULT 0 COMMENT '奖品id',
  `exchange_code` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '兑换码',
  `expire_time` int(11) NOT NULL DEFAULT 0 COMMENT '过期时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `CODE`(`exchange_code`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '兑换码表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for picture
-- ----------------------------
DROP TABLE IF EXISTS `picture`;
CREATE TABLE `picture`  (
  `picture_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '营销图片id',
  `url` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '图片URL',
  `description` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '图片描述',
  `enable` tinyint(2) NOT NULL DEFAULT 0 COMMENT '是否启用  0 禁用  1 启用',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `deleted_at` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
  PRIMARY KEY (`picture_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '营销图片表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for prize
-- ----------------------------
DROP TABLE IF EXISTS `prize`;
CREATE TABLE `prize`  (
  `prize_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '奖品id',
  `prize_name` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '奖品名称',
  `total_number` int(8) NOT NULL DEFAULT 0 COMMENT '奖品总量',
  `surplus_number` int(8) NOT NULL DEFAULT 0 COMMENT '奖品余量',
  `lock_number` int(8) NOT NULL COMMENT '锁定奖品数量',
  `prize_image` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '奖品图片',
  `description` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '描述',
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  `deleted_at` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`prize_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '奖品表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for sign
-- ----------------------------
DROP TABLE IF EXISTS `sign`;
CREATE TABLE `sign`  (
  `sign_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '签到id',
  `wx_user_id` int(11) NOT NULL DEFAULT 0 COMMENT '微信用户id',
  `ip` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'IP',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '签到时间',
  `continuous` tinyint(2) NOT NULL DEFAULT 0 COMMENT '用于统计连续签到天数  满足N天必中设置为1',
  PRIMARY KEY (`sign_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '签到表' ROW_FORMAT = Compact;

-- ----------------------------
-- Table structure for wx_user
-- ----------------------------
DROP TABLE IF EXISTS `wx_user`;
CREATE TABLE `wx_user`  (
  `wx_user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '微信用户id（不是微信的id）',
  `wx_username` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '微信号',
  `wx_nickname` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '微信昵称',
  `head` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '微信头像',
  `phone` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '手机号码',
  `is_follow` tinyint(2) NOT NULL DEFAULT 0 COMMENT '是否关注公众号',
  `draw_number` int(6) NOT NULL DEFAULT 0 COMMENT '抽奖次数',
  `sign_days` int(6) NOT NULL DEFAULT 0 COMMENT '连续签到天数',
  `cancel_exchange_number` int(6) NOT NULL DEFAULT 1 COMMENT '没天取消兑换码次数',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间（时间戳）',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '跟新时间（时间戳）',
  `deleted_at` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间（时间戳）',
  PRIMARY KEY (`wx_user_id`) USING BTREE,
  UNIQUE INDEX `WX_USERNAME`(`wx_username`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '微信用户表' ROW_FORMAT = Compact;

SET FOREIGN_KEY_CHECKS = 1;
