<?php
/**
 * pasang.php — PEMASANG SATU FAIL
 * Sistem Kejohanan Futsal Merdeka Kepala Batas 2026
 *
 * Letak fail ini dalam folder api/ dan buka dalam pelayar:
 *   https://merdeka.samfirefc.com/api/pasang.php
 *
 * Ia akan:
 *   1. Uji sambungan database
 *   2. Import kesemua jadual + 32 perlawanan (skema tertanam dalam fail ini)
 *   3. Tulis api/config.php dengan betul (tiada risiko salah taip)
 *   4. Cipta akaun Super Admin
 *   5. Padam sendiri fail pemasangan
 *
 * Ditulis dalam sintaks PHP lama supaya boleh jalan pada mana-mana versi PHP 7+.
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('SKEMA_B64', 'LS0gPT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09Ci0tICBTSVNURU0gS0VKT0hBTkFOIEZVVFNBTCBNRVJERUtBIEtFUEFMQSBCQVRBUyAyMDI2Ci0tICBTa2VtYSBEYXRhYmFzZSBNeVNRTCAvIE1hcmlhREIKLS0gIFBlbmdhbmp1cjogUHVzYXQgS2VjZW1lcmxhbmdhbiBBcy1TeWFmaWVlIChQQUtTWSksIEtlcGFsYSBCYXRhcwotLSAgVGFyaWtoIGtlam9oYW5hbjogMzAgT2dvcyAyMDI2Ci0tID09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PQotLSAgSW1wb3J0IGZhaWwgaW5pIG1lbGFsdWkgcGhwTXlBZG1pbiAoY1BhbmVsKSBrZSBkYWxhbSBkYXRhYmFzZSBrb3NvbmcuCi0tICBTZWxlcGFzIGltcG9ydCwgYnVrYSAvYXBpL3NldHVwLnBocCBzZWthbGkgdW50dWsgY2lwdGEgU3VwZXIgQWRtaW4uCi0tID09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PQoKU0VUIE5BTUVTIHV0ZjhtYjQ7ClNFVCBGT1JFSUdOX0tFWV9DSEVDS1MgPSAwOwoKRFJPUCBUQUJMRSBJRiBFWElTVFMgYXVkaXRfbG9nOwpEUk9QIFRBQkxFIElGIEVYSVNUUyBsb2dpbl9hdHRlbXB0czsKRFJPUCBUQUJMRSBJRiBFWElTVFMgcGVuZGFmdGFyYW47CkRST1AgVEFCTEUgSUYgRVhJU1RTIGRyYXc7CkRST1AgVEFCTEUgSUYgRVhJU1RTIG1hdGNoZXM7CkRST1AgVEFCTEUgSUYgRVhJU1RTIHBsYXllcnM7CkRST1AgVEFCTEUgSUYgRVhJU1RTIHRlYW1zOwpEUk9QIFRBQkxFIElGIEVYSVNUUyBhZG1pbnM7CkRST1AgVEFCTEUgSUYgRVhJU1RTIHNldHRpbmdzOwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCi0tIFRldGFwYW4gdW11bSBzaXN0ZW0gKGtleS12YWx1ZSkKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCkNSRUFURSBUQUJMRSBzZXR0aW5ncyAoCiAgayAgICAgICAgICAgICAgVkFSQ0hBUig2NCkgIE5PVCBOVUxMIFBSSU1BUlkgS0VZLAogIHYgICAgICAgICAgICAgIFRFWFQgICAgICAgICBOVUxMLAogIHVwZGF0ZWRfYXQgICAgIERBVEVUSU1FICAgICBOT1QgTlVMTCBERUZBVUxUIENVUlJFTlRfVElNRVNUQU1QCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIE9OIFVQREFURSBDVVJSRU5UX1RJTUVTVEFNUAopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X3VuaWNvZGVfY2k7CgpJTlNFUlQgSU5UTyBzZXR0aW5ncyAoaywgdikgVkFMVUVTCiAgKCduYW1hX2tlam9oYW5hbicsICAgJ0tFSk9IQU5BTiBGVVRTQUwgTUVSREVLQSBLRVBBTEEgQkFUQVMgMjAyNicpLAogICgnbmFtYV9wZW5nYW5qdXInLCAgICdTQU1GSVJFIEZDIGRlbmdhbiBrZXJqYXNhbWEgUEFLU1ksIEtlcGFsYSBCYXRhcycpLAogICgndGFyaWtoX2tlam9oYW5hbicsICcyMDI2LTA4LTMwJyksCiAgKCdtYXNhX211bGEnLCAgICAgICAgJzA4OjMwJyksCiAgKCdsb2thc2knLCAgICAgICAgICAgJ0dlbGFuZ2dhbmcgRnV0c2FsIFBBS1NZLCBLZXBhbGEgQmF0YXMsIFB1bGF1IFBpbmFuZycpLAogICgna2VwdXR1c2FuX2Rpa3VuY2knLCcwJyksCiAgKCdwZW5ndW11bWFuJywgICAgICAgJycpLAogICgncGVuZGFmdGFyYW5fYnVrYScsICcxJyksCiAgKCd5dXJhbicsICAgICAgICAgICAgJ1JNMTUwJyksCiAgKCd0ZWxlZm9uX3VydXNldGlhJywgJzAxOS0xMjMgNDU2NycpLAogICgndXJsX3dlYnNpdGUnLCAgICAgICdodHRwczovL3NhbWZpcmVmYy5jb20nKSwKICAoJ3VybF9kYWZ0YXJfYWhsaScsICAnaHR0cHM6Ly9zYW1maXJlZmMuY29tJyk7CgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KLS0gQWthdW4gYWRtaW4gKHRpYWRhIHBlbmRhZnRhcmFuIHRlcmJ1a2Eg4oCUIHNlZWQgbWVsYWx1aSBzZXR1cC5waHAgc2FoYWphKQotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KQ1JFQVRFIFRBQkxFIGFkbWlucyAoCiAgaWQgICAgICAgICAgICAgSU5UIFVOU0lHTkVEIE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5UIFBSSU1BUlkgS0VZLAogIG5hbWEgICAgICAgICAgIFZBUkNIQVIoMTAwKSBOT1QgTlVMTCwKICBlbWFpbCAgICAgICAgICBWQVJDSEFSKDE5MCkgTk9UIE5VTEwsCiAgcGFzc3dvcmRfaGFzaCAgVkFSQ0hBUigyNTUpIE5PVCBOVUxMLAogIHJvbGUgICAgICAgICAgIEVOVU0oJ2FkbWluJywnc3VwZXInKSBOT1QgTlVMTCBERUZBVUxUICdhZG1pbicsCiAgYWt0aWYgICAgICAgICAgVElOWUlOVCgxKSAgIE5PVCBOVUxMIERFRkFVTFQgMSwKICBsYXN0X2xvZ2luX2F0ICBEQVRFVElNRSAgICAgTlVMTCwKICBjcmVhdGVkX2F0ICAgICBEQVRFVElNRSAgICAgTk9UIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBVTklRVUUgS0VZIHVxX2FkbWluc19lbWFpbCAoZW1haWwpCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfdW5pY29kZV9jaTsKCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSBQZXJjdWJhYW4gbG9nIG1hc3VrIChyYXRlIGxpbWl0OiA1IGdhZ2FsIC0+IGt1bmNpIDE1IG1pbml0KQotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KQ1JFQVRFIFRBQkxFIGxvZ2luX2F0dGVtcHRzICgKICBpZCAgICAgICAgICAgICBCSUdJTlQgVU5TSUdORUQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAgZW1haWwgICAgICAgICAgVkFSQ0hBUigxOTApIE5PVCBOVUxMLAogIGlwICAgICAgICAgICAgIFZBUkNIQVIoNDUpICBOT1QgTlVMTCwKICBiZXJqYXlhICAgICAgICBUSU5ZSU5UKDEpICAgTk9UIE5VTEwgREVGQVVMVCAwLAogIGNyZWF0ZWRfYXQgICAgIERBVEVUSU1FICAgICBOT1QgTlVMTCBERUZBVUxUIENVUlJFTlRfVElNRVNUQU1QLAogIEtFWSBpZHhfbGFfZW1haWxfdGltZSAoZW1haWwsIGNyZWF0ZWRfYXQpLAogIEtFWSBpZHhfbGFfaXBfdGltZSAoaXAsIGNyZWF0ZWRfYXQpCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfdW5pY29kZV9jaTsKCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSAyNCBwYXN1a2FuOiA4IGt1bXB1bGFuIChBLUgpIHggMyBzbG90Ci0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpDUkVBVEUgVEFCTEUgdGVhbXMgKAogIGlkICAgICAgICAgICAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCBQUklNQVJZIEtFWSwKICBuYW1hICAgICAgICAgICBWQVJDSEFSKDgwKSAgTk9UIE5VTEwgREVGQVVMVCAnJywKICBzaW5na2F0YW4gICAgICBWQVJDSEFSKDEyKSAgTk9UIE5VTEwgREVGQVVMVCAnJywKICBwZW5ndXJ1cyAgICAgICBWQVJDSEFSKDgwKSAgTk9UIE5VTEwgREVGQVVMVCAnJywKICB0ZWxlZm9uICAgICAgICBWQVJDSEFSKDMwKSAgTk9UIE5VTEwgREVGQVVMVCAnJywKICBsb2dvICAgICAgICAgICBWQVJDSEFSKDEyMCkgTk9UIE5VTEwgREVGQVVMVCAnJywKICBrdW1wdWxhbiAgICAgICBDSEFSKDEpICAgICAgTk9UIE5VTEwsCiAgc2xvdCAgICAgICAgICAgVElOWUlOVCAgICAgIE5PVCBOVUxMLAogIC0tIHBlbWVjYWggc2VyaSBtYW51YWwgKHVuZGlhbikgYmlsYSBtYXRhL2JlemEgZ29sL2p1bWxhaCBnb2wvaGVhZC10by1oZWFkIHNhbWEKICAtLSBhbmdrYSBsZWJpaCBLRUNJTCA9IGtlZHVkdWthbiBsZWJpaCB0aW5nZ2kuIDAgPSBiZWx1bSBkaXRldGFwa2FuLgogIHRpZWJyZWFrICAgICAgIElOVCAgICAgICAgICBOT1QgTlVMTCBERUZBVUxUIDAsCiAgY3JlYXRlZF9hdCAgICAgREFURVRJTUUgICAgIE5PVCBOVUxMIERFRkFVTFQgQ1VSUkVOVF9USU1FU1RBTVAsCiAgVU5JUVVFIEtFWSB1cV90ZWFtc19zbG90IChrdW1wdWxhbiwgc2xvdCksCiAgS0VZIGlkeF90ZWFtc19rdW1wdWxhbiAoa3VtcHVsYW4pCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfdW5pY29kZV9jaTsKCklOU0VSVCBJTlRPIHRlYW1zIChuYW1hLCBrdW1wdWxhbiwgc2xvdCkgVkFMVUVTCiAoJycsICdBJywxKSwoJycsICdBJywyKSwoJycsICdBJywzKSwKICgnJywgJ0InLDEpLCgnJywgJ0InLDIpLCgnJywgJ0InLDMpLAogKCcnLCAnQycsMSksKCcnLCAnQycsMiksKCcnLCAnQycsMyksCiAoJycsICdEJywxKSwoJycsICdEJywyKSwoJycsICdEJywzKSwKICgnJywgJ0UnLDEpLCgnJywgJ0UnLDIpLCgnJywgJ0UnLDMpLAogKCcnLCAnRicsMSksKCcnLCAnRicsMiksKCcnLCAnRicsMyksCiAoJycsICdHJywxKSwoJycsICdHJywyKSwoJycsICdHJywzKSwKICgnJywgJ0gnLDEpLCgnJywgJ0gnLDIpLCgnJywgJ0gnLDMpOwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCi0tIFNlbmFyYWkgcGVtYWluIChvcHN5ZW5hbCkKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCkNSRUFURSBUQUJMRSBwbGF5ZXJzICgKICBpZCAgICAgICAgICAgICBJTlQgVU5TSUdORUQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAgdGVhbV9pZCAgICAgICAgSU5UIFVOU0lHTkVEIE5PVCBOVUxMLAogIG5hbWEgICAgICAgICAgIFZBUkNIQVIoODApICBOT1QgTlVMTCwKICBub19qZXJzaSAgICAgICBWQVJDSEFSKDQpICAgTk9UIE5VTEwgREVGQVVMVCAnJywKICBub19rcCAgICAgICAgICBWQVJDSEFSKDIwKSAgTk9UIE5VTEwgREVGQVVMVCAnJywKICBjcmVhdGVkX2F0ICAgICBEQVRFVElNRSAgICAgTk9UIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBLRVkgaWR4X3BsYXllcnNfdGVhbSAodGVhbV9pZCksCiAgQ09OU1RSQUlOVCBma19wbGF5ZXJzX3RlYW0gRk9SRUlHTiBLRVkgKHRlYW1faWQpCiAgICBSRUZFUkVOQ0VTIHRlYW1zKGlkKSBPTiBERUxFVEUgQ0FTQ0FERQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X3VuaWNvZGVfY2k7CgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KLS0gMzIgcGVybGF3YW5hbgotLSAgIHBlcmluZ2thdCA6IGdydXAgfCBzYSB8IHNzIHwgdGhpcmQgfCBmaW5hbAotLSAgICpfc3VtYmVyICA6IHJ1anVrYW4gcGFzdWthbiBzZWJlbHVtIGRpdGVudHVrYW4sIGNvbnRvaAotLSAgICAgICAgICAgICAgICdVTkRJOjEnICA9IGtlZHVkdWthbiBrZS0xIGhhc2lsIHVuZGlhbiBzdWt1IGFraGlyCi0tICAgICAgICAgICAgICAgJ1c6U0ExJyAgID0gcGVtZW5hbmcgcGVybGF3YW5hbiBTQTEKLS0gICAgICAgICAgICAgICAnTDpTUzEnICAgPSB5YW5nIGthbGFoIHBlcmxhd2FuYW4gU1MxCi0tICAgdmVyc2lvbiAgIDogb3B0aW1pc3RpYyBsb2NraW5nIC0gZWxhayBkdWEgYWRtaW4gdGluZGloIHNrb3IKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCkNSRUFURSBUQUJMRSBtYXRjaGVzICgKICBpZCAgICAgICAgICAgICBJTlQgVU5TSUdORUQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAga29kICAgICAgICAgICAgVkFSQ0hBUigxMCkgIE5PVCBOVUxMLAogIHBlcmluZ2thdCAgICAgIEVOVU0oJ2dydXAnLCdzYScsJ3NzJywndGhpcmQnLCdmaW5hbCcpIE5PVCBOVUxMLAogIGt1bXB1bGFuICAgICAgIENIQVIoMSkgICAgICBOVUxMLAogIHVydXRhbiAgICAgICAgIElOVCAgICAgICAgICBOT1QgTlVMTCwKICBnZWxhbmdnYW5nICAgICBUSU5ZSU5UICAgICAgTk9UIE5VTEwgREVGQVVMVCAxLAogIG1hc2FfamFkdWFsICAgIFRJTUUgICAgICAgICBOT1QgTlVMTCwKICB0ZW1wb2hfbWluaXQgICBUSU5ZSU5UICAgICAgTk9UIE5VTEwgREVGQVVMVCAxMSwKICB0ZWFtX2hvbWVfaWQgICBJTlQgVU5TSUdORUQgTlVMTCwKICB0ZWFtX2F3YXlfaWQgICBJTlQgVU5TSUdORUQgTlVMTCwKICBob21lX3N1bWJlciAgICBWQVJDSEFSKDE2KSAgTk9UIE5VTEwgREVGQVVMVCAnJywKICBhd2F5X3N1bWJlciAgICBWQVJDSEFSKDE2KSAgTk9UIE5VTEwgREVGQVVMVCAnJywKICBza29yX2hvbWUgICAgICBUSU5ZSU5UICAgICAgTlVMTCwKICBza29yX2F3YXkgICAgICBUSU5ZSU5UICAgICAgTlVMTCwKICBwZW5hbHRpX2hvbWUgICBUSU5ZSU5UICAgICAgTlVMTCwKICBwZW5hbHRpX2F3YXkgICBUSU5ZSU5UICAgICAgTlVMTCwKICBzdGF0dXMgICAgICAgICBFTlVNKCdzY2hlZHVsZWQnLCdsaXZlJywnZG9uZScpIE5PVCBOVUxMIERFRkFVTFQgJ3NjaGVkdWxlZCcsCiAgY2F0YXRhbiAgICAgICAgVkFSQ0hBUigyMDApIE5PVCBOVUxMIERFRkFVTFQgJycsCiAgdXBkYXRlZF9ieSAgICAgSU5UIFVOU0lHTkVEIE5VTEwsCiAgdXBkYXRlZF9hdCAgICAgREFURVRJTUUgICAgIE5VTEwsCiAgdmVyc2lvbiAgICAgICAgSU5UIFVOU0lHTkVEIE5PVCBOVUxMIERFRkFVTFQgMSwKICBVTklRVUUgS0VZIHVxX21hdGNoZXNfa29kIChrb2QpLAogIEtFWSBpZHhfbWF0Y2hlc191cnV0YW4gKHVydXRhbiksCiAgS0VZIGlkeF9tYXRjaGVzX3BlcmluZ2thdCAocGVyaW5na2F0KSwKICBDT05TVFJBSU5UIGZrX21faG9tZSBGT1JFSUdOIEtFWSAodGVhbV9ob21lX2lkKSBSRUZFUkVOQ0VTIHRlYW1zKGlkKSBPTiBERUxFVEUgU0VUIE5VTEwsCiAgQ09OU1RSQUlOVCBma19tX2F3YXkgRk9SRUlHTiBLRVkgKHRlYW1fYXdheV9pZCkgUkVGRVJFTkNFUyB0ZWFtcyhpZCkgT04gREVMRVRFIFNFVCBOVUxMCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfdW5pY29kZV9jaTsKCi0tIC0tLSAyNCBwZXJsYXdhbmFuIHBlcmluZ2thdCBrdW1wdWxhbiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCi0tIFN1c3VuYW4gc2xvdDogMiBnZWxhbmdnYW5nIHNlcmVudGFrLCAxMiBzbG90IHggMTQgbWluaXQgKDA4OjMwIC0gMTE6MTUpCi0tIFNldGlhcCBrdW1wdWxhbiBiZXJlaGF0IDMgc2xvdCAofjU2IG1pbml0KSBhbnRhcmEgcGVybGF3YW5hbi4KSU5TRVJUIElOVE8gbWF0Y2hlcyAoa29kLCBwZXJpbmdrYXQsIGt1bXB1bGFuLCB1cnV0YW4sIGdlbGFuZ2dhbmcsIG1hc2FfamFkdWFsLCB0ZW1wb2hfbWluaXQsIHRlYW1faG9tZV9pZCwgdGVhbV9hd2F5X2lkKSBWQUxVRVMKLS0gU2xvdCAxICAwODozMAooJ0ExJywnZ3J1cCcsJ0EnLCAxLDEsJzA4OjMwJywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0EnIEFORCBzbG90PTEpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nQScgQU5EIHNsb3Q9MikpLAooJ0IxJywnZ3J1cCcsJ0InLCAyLDIsJzA4OjMwJywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0InIEFORCBzbG90PTEpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nQicgQU5EIHNsb3Q9MikpLAotLSBTbG90IDIgIDA4OjQ0CignQzEnLCdncnVwJywnQycsIDMsMSwnMDg6NDQnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nQycgQU5EIHNsb3Q9MSksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdDJyBBTkQgc2xvdD0yKSksCignRDEnLCdncnVwJywnRCcsIDQsMiwnMDg6NDQnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRCcgQU5EIHNsb3Q9MSksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdEJyBBTkQgc2xvdD0yKSksCi0tIFNsb3QgMyAgMDg6NTgKKCdFMScsJ2dydXAnLCdFJywgNSwxLCcwODo1OCcsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdFJyBBTkQgc2xvdD0xKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0UnIEFORCBzbG90PTIpKSwKKCdGMScsJ2dydXAnLCdGJywgNiwyLCcwODo1OCcsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdGJyBBTkQgc2xvdD0xKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0YnIEFORCBzbG90PTIpKSwKLS0gU2xvdCA0ICAwOToxMgooJ0cxJywnZ3J1cCcsJ0cnLCA3LDEsJzA5OjEyJywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0cnIEFORCBzbG90PTEpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRycgQU5EIHNsb3Q9MikpLAooJ0gxJywnZ3J1cCcsJ0gnLCA4LDIsJzA5OjEyJywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0gnIEFORCBzbG90PTEpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nSCcgQU5EIHNsb3Q9MikpLAotLSBTbG90IDUgIDA5OjI2CignQTInLCdncnVwJywnQScsIDksMSwnMDk6MjYnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nQScgQU5EIHNsb3Q9MyksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdBJyBBTkQgc2xvdD0xKSksCignQjInLCdncnVwJywnQicsMTAsMiwnMDk6MjYnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nQicgQU5EIHNsb3Q9MyksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdCJyBBTkQgc2xvdD0xKSksCi0tIFNsb3QgNiAgMDk6NDAKKCdDMicsJ2dydXAnLCdDJywxMSwxLCcwOTo0MCcsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdDJyBBTkQgc2xvdD0zKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0MnIEFORCBzbG90PTEpKSwKKCdEMicsJ2dydXAnLCdEJywxMiwyLCcwOTo0MCcsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdEJyBBTkQgc2xvdD0zKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0QnIEFORCBzbG90PTEpKSwKLS0gU2xvdCA3ICAwOTo1NAooJ0UyJywnZ3J1cCcsJ0UnLDEzLDEsJzA5OjU0JywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0UnIEFORCBzbG90PTMpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRScgQU5EIHNsb3Q9MSkpLAooJ0YyJywnZ3J1cCcsJ0YnLDE0LDIsJzA5OjU0JywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0YnIEFORCBzbG90PTMpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRicgQU5EIHNsb3Q9MSkpLAotLSBTbG90IDggIDEwOjA4CignRzInLCdncnVwJywnRycsMTUsMSwnMTA6MDgnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRycgQU5EIHNsb3Q9MyksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdHJyBBTkQgc2xvdD0xKSksCignSDInLCdncnVwJywnSCcsMTYsMiwnMTA6MDgnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nSCcgQU5EIHNsb3Q9MyksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdIJyBBTkQgc2xvdD0xKSksCi0tIFNsb3QgOSAgMTA6MjIKKCdBMycsJ2dydXAnLCdBJywxNywxLCcxMDoyMicsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdBJyBBTkQgc2xvdD0yKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0EnIEFORCBzbG90PTMpKSwKKCdCMycsJ2dydXAnLCdCJywxOCwyLCcxMDoyMicsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdCJyBBTkQgc2xvdD0yKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0InIEFORCBzbG90PTMpKSwKLS0gU2xvdCAxMCAxMDozNgooJ0MzJywnZ3J1cCcsJ0MnLDE5LDEsJzEwOjM2JywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0MnIEFORCBzbG90PTIpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nQycgQU5EIHNsb3Q9MykpLAooJ0QzJywnZ3J1cCcsJ0QnLDIwLDIsJzEwOjM2JywxMSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0QnIEFORCBzbG90PTIpLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRCcgQU5EIHNsb3Q9MykpLAotLSBTbG90IDExIDEwOjUwCignRTMnLCdncnVwJywnRScsMjEsMSwnMTA6NTAnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRScgQU5EIHNsb3Q9MiksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdFJyBBTkQgc2xvdD0zKSksCignRjMnLCdncnVwJywnRicsMjIsMiwnMTA6NTAnLDExLChTRUxFQ1QgaWQgRlJPTSB0ZWFtcyBXSEVSRSBrdW1wdWxhbj0nRicgQU5EIHNsb3Q9MiksKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdGJyBBTkQgc2xvdD0zKSksCi0tIFNsb3QgMTIgMTE6MDQKKCdHMycsJ2dydXAnLCdHJywyMywxLCcxMTowNCcsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdHJyBBTkQgc2xvdD0yKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0cnIEFORCBzbG90PTMpKSwKKCdIMycsJ2dydXAnLCdIJywyNCwyLCcxMTowNCcsMTEsKFNFTEVDVCBpZCBGUk9NIHRlYW1zIFdIRVJFIGt1bXB1bGFuPSdIJyBBTkQgc2xvdD0yKSwoU0VMRUNUIGlkIEZST00gdGVhbXMgV0hFUkUga3VtcHVsYW49J0gnIEFORCBzbG90PTMpKTsKCi0tIC0tLSA4IHBlcmxhd2FuYW4gcGVyaW5na2F0IGthbGFoIG1hdGkgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpJTlNFUlQgSU5UTyBtYXRjaGVzIChrb2QsIHBlcmluZ2thdCwga3VtcHVsYW4sIHVydXRhbiwgZ2VsYW5nZ2FuZywgbWFzYV9qYWR1YWwsIHRlbXBvaF9taW5pdCwgaG9tZV9zdW1iZXIsIGF3YXlfc3VtYmVyKSBWQUxVRVMKKCdTQTEnLCdzYScsICAgTlVMTCwzMSwxLCcxMTozNScsMTEsJ1VOREk6MScsJ1VOREk6MicpLAooJ1NBMicsJ3NhJywgICBOVUxMLDMyLDIsJzExOjM1JywxMSwnVU5ESTozJywnVU5ESTo0JyksCignU0EzJywnc2EnLCAgIE5VTEwsMzMsMSwnMTE6NTUnLDExLCdVTkRJOjUnLCdVTkRJOjYnKSwKKCdTQTQnLCdzYScsICAgTlVMTCwzNCwyLCcxMTo1NScsMTEsJ1VOREk6NycsJ1VOREk6OCcpLAooJ1NTMScsJ3NzJywgICBOVUxMLDQxLDEsJzE0OjMwJywxNSwnVzpTQTEnLCdXOlNBMicpLAooJ1NTMicsJ3NzJywgICBOVUxMLDQyLDIsJzE0OjMwJywxNSwnVzpTQTMnLCdXOlNBNCcpLAooJ1QzJywndGhpcmQnLCBOVUxMLDUxLDEsJzE1OjEwJywxNSwnTDpTUzEnLCdMOlNTMicpLAooJ0ZJTkFMJywnZmluYWwnLE5VTEwsNjEsMSwnMTU6NDAnLDE1LCdXOlNTMScsJ1c6U1MyJyk7CgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KLS0gUGVuZGFmdGFyYW4gcGFzdWthbiAoYm9yYW5nIGF3YW0pIOKAlCBkaXNlbWFrICYgZGlsdWx1c2thbiBvbGVoIGFkbWluCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpDUkVBVEUgVEFCTEUgcGVuZGFmdGFyYW4gKAogIGlkICAgICAgICAgICAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCBQUklNQVJZIEtFWSwKICBuYW1hICAgICAgICAgICBWQVJDSEFSKDgwKSAgTk9UIE5VTEwsCiAgcGVuZ3VydXMgICAgICAgVkFSQ0hBUig4MCkgIE5PVCBOVUxMLAogIHRlbGVmb24gICAgICAgIFZBUkNIQVIoMzApICBOT1QgTlVMTCwKICBwZW1haW5fanNvbiAgICBURVhUICAgICAgICAgTlVMTCwKICBsb2dvICAgICAgICAgICBWQVJDSEFSKDEyMCkgTk9UIE5VTEwgREVGQVVMVCAnJywKICBzdGF0dXMgICAgICAgICBFTlVNKCdiYXJ1JywnbHVsdXMnLCd0b2xhaycpIE5PVCBOVUxMIERFRkFVTFQgJ2JhcnUnLAogIHRlYW1faWQgICAgICAgIElOVCBVTlNJR05FRCBOVUxMLAogIGNhdGF0YW4gICAgICAgIFZBUkNIQVIoMjAwKSBOT1QgTlVMTCBERUZBVUxUICcnLAogIGlwICAgICAgICAgICAgIFZBUkNIQVIoNDUpICBOT1QgTlVMTCBERUZBVUxUICcnLAogIGNyZWF0ZWRfYXQgICAgIERBVEVUSU1FICAgICBOT1QgTlVMTCBERUZBVUxUIENVUlJFTlRfVElNRVNUQU1QLAogIEtFWSBpZHhfZGFmdGFyX3N0YXR1cyAoc3RhdHVzKSwKICBLRVkgaWR4X2RhZnRhcl9pcCAoaXAsIGNyZWF0ZWRfYXQpCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfdW5pY29kZV9jaTsKCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSBVbmRpYW4gc3VrdSBha2hpciDigJQgaGFueWEgU0FUVSBiYXJpcyBkaWJlbmFya2FuIHd1anVkCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpDUkVBVEUgVEFCTEUgZHJhdyAoCiAgaWQgICAgICAgICAgICAgSU5UIFVOU0lHTkVEIE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5UIFBSSU1BUlkgS0VZLAogIGRpamFsYW5rYW5fb2xlaCBJTlQgVU5TSUdORUQgTlVMTCwKICBuYW1hX3BlbGFrc2FuYSBWQVJDSEFSKDEwMCkgTk9UIE5VTEwgREVGQVVMVCAnJywKICBoYXNpbF9qc29uICAgICBURVhUICAgICAgICAgTk9UIE5VTEwsCiAgc2VlZF9idWt0aSAgICAgVkFSQ0hBUig2NCkgIE5PVCBOVUxMIERFRkFVTFQgJycsCiAgY3JlYXRlZF9hdCAgICAgREFURVRJTUUgICAgIE5PVCBOVUxMIERFRkFVTFQgQ1VSUkVOVF9USU1FU1RBTVAKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF91bmljb2RlX2NpOwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCi0tIExvZyBha3Rpdml0aSDigJQgc2V0aWFwIHBlcnViYWhhbiBkaXJla29kCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpDUkVBVEUgVEFCTEUgYXVkaXRfbG9nICgKICBpZCAgICAgICAgICAgICBCSUdJTlQgVU5TSUdORUQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAgYWRtaW5faWQgICAgICAgSU5UIFVOU0lHTkVEIE5VTEwsCiAgYWRtaW5fbmFtYSAgICAgVkFSQ0hBUigxMDApIE5PVCBOVUxMIERFRkFVTFQgJycsCiAgdGluZGFrYW4gICAgICAgVkFSQ0hBUig2MCkgIE5PVCBOVUxMLAogIGJ1dGlyYW5fanNvbiAgIFRFWFQgICAgICAgICBOVUxMLAogIGlwICAgICAgICAgICAgIFZBUkNIQVIoNDUpICBOT1QgTlVMTCBERUZBVUxUICcnLAogIGNyZWF0ZWRfYXQgICAgIERBVEVUSU1FICAgICBOT1QgTlVMTCBERUZBVUxUIENVUlJFTlRfVElNRVNUQU1QLAogIEtFWSBpZHhfYXVkaXRfdGltZSAoY3JlYXRlZF9hdCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF91bmljb2RlX2NpOwoKU0VUIEZPUkVJR05fS0VZX0NIRUNLUyA9IDE7Cg==');

$DIR = dirname(__FILE__);

/* ------------------------------------------------------------------ util */
function nilai($k, $d = '') {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d;
}
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Pecahkan fail SQL kepada penyataan individu. */
function pecahSql($sql) {
    $baris = preg_split("/\r\n|\n|\r/", $sql);
    $keluar = array();
    $semasa = '';
    foreach ($baris as $b) {
        $t = trim($b);
        if ($t === '' || substr($t, 0, 2) === '--') { continue; }
        $semasa .= $b . "\n";
        if (substr($t, -1) === ';') {
            $keluar[] = $semasa;
            $semasa = '';
        }
    }
    if (trim($semasa) !== '') { $keluar[] = $semasa; }
    return $keluar;
}

/** Jana kandungan config.php dengan nilai di-escape secara selamat. */
function janaConfig($host, $nama, $user, $pass, $port, $appKey, $https) {
    $v = 'var_export';
    return "<?php\n"
        . "/**\n"
        . " * Konfigurasi sistem — dijana automatik oleh pasang.php\n"
        . " * Jangan edit melainkan perlu.\n"
        . " */\n\n"
        . "return [\n"
        . "    'db' => [\n"
        . "        'host' => " . $v($host, true) . ",\n"
        . "        'name' => " . $v($nama, true) . ",\n"
        . "        'user' => " . $v($user, true) . ",\n"
        . "        'pass' => " . $v($pass, true) . ",\n"
        . "        'port' => " . (int)$port . ",\n"
        . "    ],\n\n"
        . "    'app_key' => " . $v($appKey, true) . ",\n"
        . "    'https_only' => " . ($https ? 'true' : 'false') . ",\n\n"
        . "    'login_max_cuba'     => 5,\n"
        . "    'login_lock_minit'   => 15,\n"
        . "    'session_idle_minit' => 240,\n"
        . "    'timezone'           => 'Asia/Kuala_Lumpur',\n"
        . "    'dev_origin'         => '',\n"
        . "];\n";
}

function janaKunci() {
    if (function_exists('random_bytes')) { return bin2hex(random_bytes(24)); }
    return md5(uniqid('', true)) . md5(uniqid('', true));
}

/* --------------------------------------------------------------- proses */
$ralat   = array();
$langkah = array();
$siap    = false;
$configManual = '';

$f = array(
    'host'  => nilai('host', 'localhost'),
    'nama'  => nilai('nama'),
    'user'  => nilai('user'),
    'pass'  => isset($_POST['pass']) ? (string)$_POST['pass'] : '',
    'port'  => nilai('port', '3306'),
    'anama' => nilai('anama'),
    'aemel' => nilai('aemel'),
    'apass' => isset($_POST['apass']) ? (string)$_POST['apass'] : '',
    'apass2'=> isset($_POST['apass2']) ? (string)$_POST['apass2'] : '',
);

$hantar = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($hantar) {

    /* ---- 1. Semak versi PHP ---- */
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $ralat[] = 'Server ini guna PHP ' . PHP_VERSION . '. Sistem perlukan PHP 7.4 atau lebih baharu. '
                 . 'Tukar di cPanel &rarr; MultiPHP Manager &rarr; pilih domain ini &rarr; PHP 8.1.';
    }
    foreach (array('pdo_mysql', 'mbstring', 'json', 'session') as $ext) {
        if (!extension_loaded($ext)) {
            $ralat[] = 'Sambungan PHP <code>' . $ext . '</code> tidak aktif. Aktifkan di cPanel &rarr; Select PHP Version &rarr; Extensions.';
        }
    }

    /* ---- 2. Semak borang ---- */
    if ($f['nama'] === '')  { $ralat[] = 'Sila isi nama database.'; }
    if ($f['user'] === '')  { $ralat[] = 'Sila isi pengguna database.'; }
    if ($f['anama'] === '') { $ralat[] = 'Sila isi nama penuh admin.'; }
    if (!filter_var($f['aemel'], FILTER_VALIDATE_EMAIL)) { $ralat[] = 'Emel admin tidak sah.'; }
    if (strlen($f['apass']) < 8) { $ralat[] = 'Kata laluan admin mesti sekurang-kurangnya 8 aksara.'; }
    if ($f['apass'] !== $f['apass2']) { $ralat[] = 'Kata laluan admin tidak sepadan.'; }

    /* ---- 3. Sambung database ---- */
    $pdo = null;
    if (!$ralat) {
        try {
            $dsn = 'mysql:host=' . $f['host'] . ';port=' . (int)$f['port'] . ';dbname=' . $f['nama'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $f['user'], $f['pass'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
            $langkah[] = 'Berjaya sambung ke database <strong>' . esc($f['nama']) . '</strong>.';
        } catch (Exception $e) {
            $ralat[] = 'Tidak dapat sambung ke database: <code>' . esc($e->getMessage()) . '</code>';
        }
    }

    /* ---- 3b. PENGADANG: jangan sekali-kali padam data hidup ---- */
    if (!$ralat && $pdo) {
        $adaData = 0;
        foreach (array('pendaftaran', 'players') as $t) {
            try { $adaData += (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
            catch (Exception $e) { /* jadual belum wujud */ }
        }
        try { $adaData += (int)$pdo->query("SELECT COUNT(*) FROM teams WHERE nama <> ''")->fetchColumn(); }
        catch (Exception $e) {}
        try { $adaData += (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE status <> 'scheduled'")->fetchColumn(); }
        catch (Exception $e) {}

        if ($adaData > 0 && trim((string)($_POST['sahkan_padam'] ?? '')) !== 'PADAM SEMUA DATA') {
            $ralat[] = '<strong>DIHENTIKAN UNTUK KESELAMATAN.</strong> Database ini sudah mengandungi data hidup '
                     . '(' . $adaData . ' rekod: pendaftaran / pasukan / pemain / keputusan). '
                     . 'Meneruskan akan <strong>MEMADAM SEMUANYA</strong>.<br><br>'
                     . 'Jika tuan benar-benar mahu pasang semula dari kosong, taip <code>PADAM SEMUA DATA</code> '
                     . 'dalam ruangan pengesahan di bawah. Jika tidak, tutup halaman ini dan padam fail '
                     . '<code>api/pasang.php</code> dari server.';
            $perluSahkanPadam = true;
        }
    }

    /* ---- 4. Import skema ---- */
    if (!$ralat && $pdo) {
        $sql = base64_decode(SKEMA_B64);
        if ($sql === false || strlen($sql) < 100) {
            $ralat[] = 'Skema database tidak dapat dibaca dari fail pemasang.';
        } else {
            try {
                $penyataan = pecahSql($sql);
                $bil = 0;
                foreach ($penyataan as $p) {
                    if (trim($p) === '') { continue; }
                    $pdo->exec($p);
                    $bil++;
                }
                $bilM = (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
                $bilT = (int)$pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
                if ($bilM !== 32 || $bilT !== 24) {
                    $ralat[] = 'Import selesai tetapi data tidak lengkap (' . $bilM . ' perlawanan, ' . $bilT . ' pasukan). Sepatutnya 32 dan 24.';
                } else {
                    $langkah[] = 'Import database selesai — ' . $bil . ' penyataan SQL, 8 jadual, 32 perlawanan, 24 slot pasukan.';
                }
            } catch (Exception $e) {
                $ralat[] = 'Ralat semasa import database: <code>' . esc($e->getMessage()) . '</code>';
            }
        }
    }

    /* ---- 5. Tulis config.php ---- */
    if (!$ralat) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $isi = janaConfig($f['host'], $f['nama'], $f['user'], $f['pass'], $f['port'], janaKunci(), $https);
        $laluan = $DIR . '/config.php';
        $tulis = @file_put_contents($laluan, $isi);
        if ($tulis === false) {
            $configManual = $isi;
            $ralat[] = 'Tidak dapat menulis <code>api/config.php</code> (kebenaran fail). '
                     . 'Salin kandungan di bawah dan tampal secara manual melalui File Manager.';
        } else {
            $semak = @include $laluan;
            if (!is_array($semak) || !isset($semak['db']['name'])) {
                $ralat[] = 'config.php ditulis tetapi tidak dapat dibaca semula. Sila hubungi pembangun.';
            } else {
                $langkah[] = 'Fail <code>api/config.php</code> ditulis dengan betul.';
            }
        }
    }

    /* ---- 6. Cipta Super Admin ---- */
    if (!$ralat && $pdo) {
        try {
            $adaAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($adaAdmin > 0) {
                $pdo->prepare('DELETE FROM admins')->execute();
            }
            $st = $pdo->prepare('INSERT INTO admins (nama, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $st->execute(array($f['anama'], strtolower($f['aemel']), password_hash($f['apass'], PASSWORD_BCRYPT), 'super'));
            $langkah[] = 'Akaun Super Admin dicipta untuk <strong>' . esc($f['aemel']) . '</strong>.';
            $siap = true;
        } catch (Exception $e) {
            $ralat[] = 'Ralat mencipta akaun admin: <code>' . esc($e->getMessage()) . '</code>';
        }
    }

    /* ---- 7. Bersihkan fail pemasangan ---- */
    if ($siap) {
        $dipadam = array();
        foreach (array('setup.php', 'semak.php', 'pasang.php') as $fn) {
            $p = $DIR . '/' . $fn;
            if (file_exists($p) && @unlink($p)) { $dipadam[] = $fn; }
        }
        if ($dipadam) {
            $langkah[] = 'Fail pemasangan dipadam automatik: ' . implode(', ', $dipadam) . '.';
        } else {
            $langkah[] = '<strong>Penting:</strong> sila padam <code>api/pasang.php</code>, <code>api/setup.php</code> dan <code>api/semak.php</code> secara manual melalui File Manager.';
        }
    }
}

/* ---- maklumat server untuk paparan ---- */
$verOk = version_compare(PHP_VERSION, '7.4.0', '>=');
?><!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pemasang — Kejohanan Futsal Merdeka Kepala Batas 2026</title>
<style>
 *{box-sizing:border-box}
 body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f5f5f4;color:#1c1917;padding:24px 16px}
 .w{max-width:620px;margin:0 auto}
 .c{background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
 h1{font-size:19px;margin:0 0 4px;color:#7B1E2B}
 p.s{margin:0 0 18px;color:#78716c;font-size:13px}
 h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#78716c;margin:22px 0 10px;padding-bottom:6px;border-bottom:1px solid #f5f5f4}
 label{display:block;font-size:12.5px;font-weight:600;margin:12px 0 5px}
 input{width:100%;padding:10px 12px;border:1px solid #d6d3d1;border-radius:9px;font-size:15px;background:#fff}
 input:focus{outline:2px solid #7B1E2B;outline-offset:1px;border-color:#7B1E2B}
 .hint{font-size:11.5px;color:#a8a29e;margin-top:4px}
 button{margin-top:22px;width:100%;padding:14px;background:#7B1E2B;color:#fff;border:0;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}
 button:hover{background:#5f1721}
 .err,.ok,.warn{padding:13px 15px;border-radius:10px;font-size:13.5px;margin-bottom:14px;line-height:1.5}
 .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
 .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
 .warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
 ul{margin:6px 0 0;padding-left:20px}
 li{margin:3px 0}
 code{background:rgba(0,0,0,.06);padding:1px 5px;border-radius:4px;font-size:12px;word-break:break-all}
 textarea{width:100%;height:220px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;padding:10px;border:1px solid #d6d3d1;border-radius:9px}
 a{color:#7B1E2B;font-weight:600}
 .row{display:flex;gap:10px}
 .row>div{flex:1}
 .pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:#f5f5f4;color:#57534e}
</style>
</head>
<body>
<div class="w">
  <div class="c">
    <h1>Pemasang Sistem Kejohanan</h1>
    <p class="s">
      Merdeka Kepala Batas 2026 &middot; PAKSY
      &nbsp;<span class="pill">PHP <?php echo esc(PHP_VERSION); ?></span>
    </p>

<?php if ($siap): ?>

    <div class="ok">
      <strong>Pemasangan selesai.</strong>
      <ul><?php foreach ($langkah as $l) { echo '<li>' . $l . '</li>'; } ?></ul>
    </div>
    <p style="font-size:14px;line-height:2">
      &rarr; <a href="../admin.html">Log masuk Panel Admin</a><br>
      &rarr; <a href="../">Buka paparan awam</a>
    </p>
    <p style="font-size:12.5px;color:#78716c;margin-top:16px">
      Log masuk guna emel <strong><?php echo esc($f['aemel']); ?></strong> dan kata laluan yang tuan taip tadi.
      Selepas log masuk, pergi tab <strong>Akaun</strong> untuk tambah admin urus setia yang lain.
    </p>

<?php else: ?>

  <?php if (!$verOk): ?>
    <div class="warn">
      Server ini guna <strong>PHP <?php echo esc(PHP_VERSION); ?></strong>. Sistem perlukan PHP 7.4 ke atas.<br>
      Tukar dahulu di cPanel &rarr; <strong>MultiPHP Manager</strong> &rarr; pilih domain ini &rarr; pilih <strong>PHP 8.1</strong> &rarr; Apply.
    </div>
  <?php endif; ?>

  <?php if ($ralat): ?>
    <div class="err">
      <strong>Belum berjaya:</strong>
      <ul><?php foreach ($ralat as $r) { echo '<li>' . $r . '</li>'; } ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($langkah && $ralat): ?>
    <div class="ok"><ul><?php foreach ($langkah as $l) { echo '<li>' . $l . '</li>'; } ?></ul></div>
  <?php endif; ?>

  <?php if ($configManual !== ''): ?>
    <p style="font-size:13px">Salin semua ini, kemudian tampal ke dalam <code>api/config.php</code> melalui File Manager (padam kandungan lama dahulu):</p>
    <textarea onclick="this.select()"><?php echo esc($configManual); ?></textarea>
  <?php endif; ?>

    <form method="post" autocomplete="off">
      <h2>Butiran Database</h2>
      <p class="hint" style="margin-top:0">Dari cPanel &rarr; MySQL&reg; Databases. Salin tepat, termasuk awalan seperti <code>adampowe_</code>.</p>

      <label>Nama database</label>
      <input name="nama" required value="<?php echo esc($f['nama']); ?>" placeholder="adampowe_merdeka">

      <label>Pengguna database</label>
      <input name="user" required value="<?php echo esc($f['user']); ?>" placeholder="adampowe_merdekauser">

      <label>Kata laluan database</label>
      <input name="pass" type="text" required value="<?php echo esc($f['pass']); ?>" placeholder="kata laluan pengguna database">
      <p class="hint">Sengaja dipapar supaya tuan boleh semak tiada salah taip. Ia tidak disimpan di mana-mana selain <code>config.php</code>.</p>

      <div class="row">
        <div>
          <label>Host</label>
          <input name="host" value="<?php echo esc($f['host']); ?>" placeholder="localhost">
        </div>
        <div>
          <label>Port</label>
          <input name="port" value="<?php echo esc($f['port']); ?>" placeholder="3306">
        </div>
      </div>

      <h2>Akaun Super Admin</h2>
      <p class="hint" style="margin-top:0">Akaun utama tuan untuk masuk panel admin.</p>

      <label>Nama penuh</label>
      <input name="anama" required value="<?php echo esc($f['anama']); ?>" placeholder="Waffiy Rosli">

      <label>Emel (untuk log masuk)</label>
      <input name="aemel" type="email" required value="<?php echo esc($f['aemel']); ?>" placeholder="nama@contoh.com">

      <label>Kata laluan (minimum 8 aksara)</label>
      <input name="apass" type="password" required minlength="8">

      <label>Ulang kata laluan</label>
      <input name="apass2" type="password" required minlength="8">

<?php if ($perluSahkanPadam): ?>
      <div class="err" style="margin-top:18px">
        <strong>Amaran:</strong> database sudah ada data. Untuk pasang semula dari kosong,
        taip tepat <code>PADAM SEMUA DATA</code> di bawah. Semua pendaftaran, pasukan,
        pemain dan keputusan akan hilang.
      </div>
      <label>Pengesahan padam</label>
      <input name="sahkan_padam" placeholder="PADAM SEMUA DATA" autocomplete="off">
<?php endif; ?>

      <button type="submit">Pasang Sistem Sekarang</button>
      <p class="hint" style="text-align:center;margin-top:12px">
        Pemasang akan import database, tulis <code>config.php</code>, cipta akaun admin, dan padam dirinya sendiri.
      </p>
    </form>

<?php endif; ?>
  </div>
</div>
</body>
</html>
