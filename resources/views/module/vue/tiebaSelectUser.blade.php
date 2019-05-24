@section('body-module')
    @parent
    <template id="select-user-template">
        <div class="col-4 input-group">
            <select v-model="selectBy" class="form-control col-4">
                <option value="uid">UID</option>
                <option value="name">用户名</option>
                <option value="displayName">覆盖名</option>
            </select>
            <keep-alive>
                <input v-if="selectBy == 'uid'" v-model="selectValue[selectByOptionsName.uid]"
                       type="number" placeholder="4000000000" aria-label="UID" class="form-control col">
                <input v-else-if="selectBy == 'name'" v-model="selectValue[selectByOptionsName.name]"
                       type="text" placeholder="n0099" aria-label="用户名" class="form-control col">
                <input v-else-if="selectBy == 'displayName'" v-model="selectValue[selectByOptionsName.displayName]"
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
            model: {
                prop: 'selectValue',
                event: 'select-value-changed'
            },
            props: {
                selectByOptionsName: {
                    type: Object,
                    default: function () {
                        return {
                            uid: 'uid',
                            name: 'name',
                            displayName: 'displayName'
                        };
                    }
                },
                selectValue: {
                    type: Object,
                    default: function () {
                        return { name: '' };
                    }
                }
            },
            data: function () {
                return {
                    selectBy: 'name'
                }
            },
            watch: {
                selectBy: function (selectBy) {
                    this.$data.selectValue = { [selectBy]: null }; // empty value to prevent old value remains after select by changed
                    this.$emit('select-value-changed', this.$data.selectValue);
                },
                selectValue: {
                    handler: function (selectValue) {
                        this.$emit('select-value-changed', selectValue);
                    },
                    deep: true
                }
            },
            mounted: function () {

            }
        });
    </script>
@endsection