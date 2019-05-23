@section('body-module')
    @parent
    <template id="select-user-template">
        <div class="col-4 input-group">
            <select v-model="selectUserBy" class="form-control col-4">
                <option value="uid">UID</option>
                <option value="name">用户名</option>
                <option value="displayName">覆盖名</option>
            </select>
            <keep-alive>
                <input v-if="selectUserBy == 'uid'" v-model="selectData[selectUserByOptionsName.uid]"
                       type="number" placeholder="123456" aria-label="UID" class="form-control col">
                <input v-else-if="selectUserBy == 'name'" v-model="selectData[selectUserByOptionsName.name]"
                       type="text" placeholder="n0099" aria-label="用户名" class="form-control col">
                <input v-else-if="selectUserBy == 'displayName'" v-model="selectData[selectUserByOptionsName.displayName]"
                       type="text" placeholder="神奇🍀" aria-label="覆盖名" class="form-control col">
            </keep-alive>
        </div>
    </template>
@endsection

@section('script-module')
    @parent
    <script>
        'use strict';

        const userSelectFormComponent = Vue.component('select-user', {
            template: '#select-user-template',
            props: {
                selectUserByOptionsName: {
                    type: Object,
                    default: function () {
                        return {
                            uid: 'uid',
                            name: 'name',
                            displayName: 'displayName'
                        };
                    }
                }
            },
            data: function () {
                return {
                    selectUserBy: 'name',
                    selectData: {}
                }
            },
            watch: {
                selectUserBy: function () {
                    this.$data.selectData = {};
                },
                selectData: {
                    handler: function (selectData) {
                        this.$emit('select-user-changed', selectData);
                    },
                    deep: true
                }
            }
        });
    </script>
@endsection